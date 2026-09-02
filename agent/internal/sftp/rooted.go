package sftp

import (
    "errors"
    "io"
    "os"
    "path/filepath"
    "strings"
    "time"

    pkgSFTP "github.com/pkg/sftp"
)

type rootedFS struct{ root string }
func newRootedFS(root string)(*rootedFS,error){abs,err:=filepath.Abs(root);if err!=nil{return nil,err};real,err:=filepath.EvalSymlinks(abs);if err!=nil{return nil,err};return &rootedFS{root:real},nil}
func(f *rootedFS)path(name string,create bool)(string,error){clean:=filepath.Clean("/"+strings.ReplaceAll(name,"\\","/"));candidate:=filepath.Join(f.root,filepath.FromSlash(strings.TrimPrefix(clean,"/")));parent:=candidate;if create{parent=filepath.Dir(candidate)};realParent,err:=filepath.EvalSymlinks(parent);if err!=nil{return "",err};rel,err:=filepath.Rel(f.root,realParent);if err!=nil||rel==".."||strings.HasPrefix(rel,".."+string(os.PathSeparator)){return "",errors.New("path escapes server root")};if create{return filepath.Join(realParent,filepath.Base(candidate)),nil};real,err:=filepath.EvalSymlinks(candidate);if err!=nil{return "",err};rel,err=filepath.Rel(f.root,real);if err!=nil||rel==".."||strings.HasPrefix(rel,".."+string(os.PathSeparator)){return "",errors.New("path escapes server root")};return real,nil}
func(f *rootedFS)Fileread(r *pkgSFTP.Request)(io.ReaderAt,error){p,e:=f.path(r.Filepath,false);if e!=nil{return nil,e};return os.Open(p)}
func(f *rootedFS)Filewrite(r *pkgSFTP.Request)(io.WriterAt,error){p,e:=f.path(r.Filepath,true);if e!=nil{return nil,e};flags:=r.Pflags();mode:=os.O_WRONLY;if flags.Creat{mode|=os.O_CREATE};if flags.Trunc{mode|=os.O_TRUNC};if flags.Append{mode|=os.O_APPEND};if flags.Excl{mode|=os.O_EXCL};return os.OpenFile(p,mode,0640)}
func(f *rootedFS)Filecmd(r *pkgSFTP.Request)error{switch r.Method{case"Setstat":p,e:=f.path(r.Filepath,false);if e!=nil{return e};flags:=r.AttrFlags();attrs:=r.Attributes();if flags.Permissions{if e=os.Chmod(p,attrs.FileMode().Perm());e!=nil{return e}};if flags.Size{if e=os.Truncate(p,int64(attrs.Size));e!=nil{return e}};if flags.Acmodtime{return os.Chtimes(p,time.Unix(int64(attrs.Atime),0),time.Unix(int64(attrs.Mtime),0))};return nil;case"Rename","PosixRename":a,e:=f.path(r.Filepath,false);if e!=nil{return e};b,e:=f.path(r.Target,true);if e!=nil{return e};return os.Rename(a,b);case"Rmdir","Remove":p,e:=f.path(r.Filepath,false);if e!=nil{return e};return os.Remove(p);case"Mkdir":p,e:=f.path(r.Filepath,true);if e!=nil{return e};return os.Mkdir(p,0750);case"Symlink","Link":return errors.New("links are disabled for SFTP isolation");case"StatVFS":return errors.New("statvfs is not supported")};return errors.New("unsupported SFTP operation")}
func(f *rootedFS)Filelist(r *pkgSFTP.Request)(pkgSFTP.ListerAt,error){p,e:=f.path(r.Filepath,false);if e!=nil{return nil,e};if r.Method=="Stat"||r.Method=="Lstat"{i,e:=os.Stat(p);if e!=nil{return nil,e};return fileInfoLister{i},nil};items,e:=os.ReadDir(p);if e!=nil{return nil,e};out:=make([]os.FileInfo,0,len(items));for _,x:=range items{i,e:=x.Info();if e==nil{out=append(out,i)}};return fileInfoLister(out),nil}
type fileInfoLister []os.FileInfo
func(l fileInfoLister)ListAt(dst []os.FileInfo,off int64)(int,error){if off>=int64(len(l)){return 0,io.EOF};n:=copy(dst,l[off:]);if int(off)+n>=len(l){return n,io.EOF};return n,nil}
