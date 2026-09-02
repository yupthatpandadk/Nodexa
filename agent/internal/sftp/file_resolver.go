package sftp

import (
    "encoding/json"
    "errors"
    "os"
    "sync"
    "time"
)

type FileResolver struct {
    Path string
    mu sync.RWMutex
    modified time.Time
    credentials map[string]Credential
}

func NewFileResolver(path string) *FileResolver {
    return &FileResolver{Path: path, credentials: map[string]Credential{}}
}

func (r *FileResolver) ResolveSFTP(username string) (Credential, error) {
    if err := r.reload(); err != nil { return Credential{}, err }
    r.mu.RLock(); defer r.mu.RUnlock()
    c, ok := r.credentials[username]
    if !ok { return Credential{}, errors.New("unknown SFTP account") }
    return c, nil
}

func (r *FileResolver) reload() error {
    info, err := os.Stat(r.Path)
    if err != nil {
        if os.IsNotExist(err) { return nil }
        return err
    }
    r.mu.RLock(); same := !info.ModTime().After(r.modified); r.mu.RUnlock()
    if same { return nil }
    b, err := os.ReadFile(r.Path); if err != nil { return err }
    var list []Credential
    if err := json.Unmarshal(b, &list); err != nil { return err }
    next := make(map[string]Credential, len(list))
    for _, c := range list {
        if c.Username != "" && c.Password != "" && c.ServerUUID != "" { next[c.Username] = c }
    }
    r.mu.Lock(); r.credentials = next; r.modified = info.ModTime(); r.mu.Unlock()
    return nil
}
