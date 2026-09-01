package docker

import (
    "archive/tar"
    "compress/gzip"
    "context"
    "errors"
    "fmt"
    "io"
    "os"
    "path/filepath"
    "sort"
    "strings"
    "time"

    "github.com/docker/docker/api/types/container"
    "github.com/docker/docker/errdefs"
)

type BackupInfo struct {
    Name       string    `json:"name"`
    Size       int64     `json:"size"`
    ModifiedAt time.Time `json:"modified_at"`
}

func (m *Manager) RenamePath(id, from, to string) error {
    src, err := m.SafePath(id, from)
    if err != nil { return err }
    dst, err := m.SafePath(id, to)
    if err != nil { return err }
    if src == dst { return nil }
    if _, err := os.Stat(src); err != nil { return err }
    if _, err := os.Stat(dst); err == nil { return errors.New("destination already exists") } else if !os.IsNotExist(err) { return err }
    if err := os.MkdirAll(filepath.Dir(dst), 0750); err != nil { return err }
    return os.Rename(src, dst)
}

func (m *Manager) ArchivePath(id, relative string) (string, error) {
    src, err := m.SafePath(id, relative)
    if err != nil { return "", err }
    info, err := os.Stat(src)
    if err != nil { return "", err }
    base := filepath.Base(src)
    dest := filepath.Join(filepath.Dir(src), base+".tar.gz")
    if strings.HasSuffix(strings.ToLower(base), ".tar.gz") || strings.HasSuffix(strings.ToLower(base), ".tgz") {
        return "", errors.New("path is already an archive")
    }
    out, err := os.Create(dest)
    if err != nil { return "", err }
    success := false
    defer func(){ _ = out.Close(); if !success { _ = os.Remove(dest) } }()
    gz := gzip.NewWriter(out)
    tw := tar.NewWriter(gz)
    walkRoot := src
    parent := filepath.Dir(src)
    err = filepath.Walk(walkRoot, func(path string, fi os.FileInfo, walkErr error) error {
        if walkErr != nil { return walkErr }
        if path == dest { return nil }
        rel, err := filepath.Rel(parent, path); if err != nil { return err }
        hdr, err := tar.FileInfoHeader(fi, ""); if err != nil { return err }
        hdr.Name = filepath.ToSlash(rel)
        if err := tw.WriteHeader(hdr); err != nil { return err }
        if fi.Mode().IsRegular() {
            f, err := os.Open(path); if err != nil { return err }
            _, copyErr := io.Copy(tw, f); closeErr := f.Close()
            if copyErr != nil { return copyErr }; return closeErr
        }
        return nil
    })
    closeTar := tw.Close(); closeGzip := gz.Close(); closeOut := out.Close()
    if err != nil { return "", err }; if closeTar != nil { return "", closeTar }; if closeGzip != nil { return "", closeGzip }; if closeOut != nil { return "", closeOut }
    success = true
    _ = info
    root, _ := m.serverRoot(id)
    rel, _ := filepath.Rel(root, dest)
    return "/" + filepath.ToSlash(rel), nil
}

func extractTarGz(archive, destination string, clearDestination bool) error {
    in, err := os.Open(archive); if err != nil { return err }; defer in.Close()
    gz, err := gzip.NewReader(in); if err != nil { return err }; defer gz.Close()
    if clearDestination {
        entries, err := os.ReadDir(destination); if err != nil && !os.IsNotExist(err) { return err }
        for _, entry := range entries { if err := os.RemoveAll(filepath.Join(destination, entry.Name())); err != nil { return err } }
    }
    if err := os.MkdirAll(destination, 0750); err != nil { return err }
    tr := tar.NewReader(gz)
    cleanRoot, err := filepath.Abs(destination); if err != nil { return err }
    for {
        hdr, err := tr.Next(); if errors.Is(err, io.EOF) { break }; if err != nil { return err }
        name := filepath.Clean(filepath.FromSlash(hdr.Name))
        if name == "." || filepath.IsAbs(name) || name == ".." || strings.HasPrefix(name, ".."+string(os.PathSeparator)) { return fmt.Errorf("unsafe archive path %q", hdr.Name) }
        target := filepath.Join(cleanRoot, name)
        rel, err := filepath.Rel(cleanRoot, target); if err != nil || rel == ".." || strings.HasPrefix(rel, ".."+string(os.PathSeparator)) { return fmt.Errorf("archive path escapes destination: %q", hdr.Name) }
        switch hdr.Typeflag {
        case tar.TypeDir:
            if err := os.MkdirAll(target, 0750); err != nil { return err }
        case tar.TypeReg, tar.TypeRegA:
            if err := os.MkdirAll(filepath.Dir(target), 0750); err != nil { return err }
            f, err := os.OpenFile(target, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0640); if err != nil { return err }
            _, copyErr := io.Copy(f, tr); closeErr := f.Close(); if copyErr != nil { return copyErr }; if closeErr != nil { return closeErr }
        default:
            return fmt.Errorf("unsupported archive entry %q", hdr.Name)
        }
    }
    return nil
}

func (m *Manager) ExtractPath(id, relative string) error {
    archive, err := m.SafePath(id, relative); if err != nil { return err }
    lower := strings.ToLower(archive); if !strings.HasSuffix(lower, ".tar.gz") && !strings.HasSuffix(lower, ".tgz") { return errors.New("only .tar.gz and .tgz archives are supported") }
    return extractTarGz(archive, filepath.Dir(archive), false)
}

func safeBackupName(name string) (string, error) {
    name = filepath.Base(strings.TrimSpace(name))
    if name == "" || name == "." || name == ".." || strings.ContainsAny(name, "/\\") { return "", errors.New("invalid backup name") }
    if !strings.HasSuffix(strings.ToLower(name), ".tar.gz") { name += ".tar.gz" }
    return name, nil
}

func (m *Manager) backupDir(id string) (string, error) {
    if _, err := m.serverRoot(id); err != nil { return "", err }
    dir := filepath.Join(m.dataRoot, ".backups", id)
    if err := os.MkdirAll(dir, 0750); err != nil { return "", err }
    return dir, nil
}

func (m *Manager) ListBackups(id string) ([]BackupInfo, error) {
    dir, err := m.backupDir(id); if err != nil { return nil, err }
    entries, err := os.ReadDir(dir); if err != nil { return nil, err }
    out := make([]BackupInfo, 0, len(entries))
    for _, entry := range entries {
        if entry.IsDir() || !strings.HasSuffix(strings.ToLower(entry.Name()), ".tar.gz") { continue }
        info, err := entry.Info(); if err != nil { continue }
        out = append(out, BackupInfo{Name: entry.Name(), Size: info.Size(), ModifiedAt: info.ModTime()})
    }
    sort.Slice(out, func(i,j int) bool { return out[i].ModifiedAt.After(out[j].ModifiedAt) })
    return out, nil
}

func (m *Manager) BackupPath(id, name string) (string, error) {
    dir, err := m.backupDir(id); if err != nil { return "", err }
    safe, err := safeBackupName(name); if err != nil { return "", err }
    path := filepath.Join(dir, safe)
    if _, err := os.Stat(path); err != nil { return "", err }
    return path, nil
}

func (m *Manager) DeleteBackup(id, name string) error {
    path, err := m.BackupPath(id, name); if err != nil { return err }
    return os.Remove(path)
}

func (m *Manager) RestoreBackup(ctx context.Context, id, name string) error {
    archive, err := m.BackupPath(id, name); if err != nil { return err }
    root, err := m.serverRoot(id); if err != nil { return err }
    containerName := "nx-" + id
    if inspect, inspectErr := m.cli.ContainerInspect(ctx, containerName); inspectErr == nil {
        if inspect.State.Running { t:=10; if err:=m.cli.ContainerStop(ctx, containerName, container.StopOptions{Timeout:&t}); err!=nil { return err } }
    } else if !errdefs.IsNotFound(inspectErr) { return inspectErr }
    return extractTarGz(archive, root, true)
}
