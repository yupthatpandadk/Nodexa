package sftp

import (
    "crypto/ed25519"
    "crypto/rand"
    "crypto/subtle"
    "encoding/pem"
    "errors"
    "fmt"
    "io"
    "log"
    "net"
    "os"
    "path/filepath"
    "strings"

    pkgSFTP "github.com/pkg/sftp"
    "golang.org/x/crypto/ssh"
)

type Credential struct { Username string; Password string; ServerUUID string }
type Resolver interface { ResolveSFTP(username string) (Credential, error) }
type StaticResolver struct { Credentials []Credential }
func (r StaticResolver) ResolveSFTP(username string) (Credential,error){for _,c:=range r.Credentials{if subtle.ConstantTimeCompare([]byte(c.Username),[]byte(username))==1{return c,nil}};return Credential{},errors.New("unknown SFTP account")}
type Server struct { Listen,DataRoot,HostKeyPath string; Resolver Resolver }
func(s *Server)Run()error{signer,err:=loadOrCreateHostKey(s.HostKeyPath);if err!=nil{return fmt.Errorf("sftp host key: %w",err)};cfg:=&ssh.ServerConfig{PasswordCallback:s.passwordCallback};cfg.AddHostKey(signer);ln,err:=net.Listen("tcp",s.Listen);if err!=nil{return err};log.Printf("Nodexa SFTP listening on %s",s.Listen);for{conn,err:=ln.Accept();if err!=nil{return err};go s.handle(conn,cfg)}}
func(s *Server)passwordCallback(meta ssh.ConnMetadata,pass []byte)(*ssh.Permissions,error){if s.Resolver==nil{return nil,errors.New("SFTP authentication unavailable")};cred,err:=s.Resolver.ResolveSFTP(meta.User());if err!=nil||subtle.ConstantTimeCompare([]byte(cred.Password),pass)!=1{return nil,errors.New("invalid SFTP credentials")};if cred.ServerUUID==""||strings.ContainsAny(cred.ServerUUID,`/\\`){return nil,errors.New("invalid SFTP server scope")};resolvedRoot,err:=filepath.Abs(filepath.Join(s.DataRoot,cred.ServerUUID));if err!=nil{return nil,err};dataRoot,err:=filepath.Abs(s.DataRoot);if err!=nil{return nil,err};rel,err:=filepath.Rel(dataRoot,resolvedRoot);if err!=nil||rel==".."||strings.HasPrefix(rel,".."+string(os.PathSeparator)){return nil,errors.New("invalid SFTP root")};if err:=os.MkdirAll(resolvedRoot,0750);err!=nil{return nil,err};return &ssh.Permissions{Extensions:map[string]string{"nodexa_root":resolvedRoot}},nil}
func(s *Server)handle(raw net.Conn,cfg *ssh.ServerConfig){defer raw.Close();conn,chans,reqs,err:=ssh.NewServerConn(raw,cfg);if err!=nil{return};defer conn.Close();go ssh.DiscardRequests(reqs);root:=conn.Permissions.Extensions["nodexa_root"];for ch:=range chans{if ch.ChannelType()!="session"{_=ch.Reject(ssh.UnknownChannelType,"session only");continue};channel,requests,err:=ch.Accept();if err!=nil{continue};go func(){defer channel.Close();for req:=range requests{if req.Type!="subsystem"||!strings.Contains(string(req.Payload),"sftp"){_=req.Reply(false,nil);continue};fs,err:=newRootedFS(root);if err!=nil{_=req.Reply(false,nil);return};_=req.Reply(true,nil);handlers:=pkgSFTP.Handlers{FileGet:fs,FilePut:fs,FileCmd:fs,FileList:fs};server:=pkgSFTP.NewRequestServer(channel,handlers);if err:=server.Serve();err!=nil&&err!=io.EOF{log.Printf("SFTP session: %v",err)};_=server.Close();return}}()}}
func loadOrCreateHostKey(path string)(ssh.Signer,error){if b,err:=os.ReadFile(path);err==nil{return ssh.ParsePrivateKey(b)};if err:=os.MkdirAll(filepath.Dir(path),0700);err!=nil{return nil,err};_,key,err:=ed25519.GenerateKey(rand.Reader);if err!=nil{return nil,err};b,err:=ssh.MarshalPrivateKey(key,"Nodexa SFTP");if err!=nil{return nil,err};encoded:=pem.EncodeToMemory(b);if err:=os.WriteFile(path,encoded,0600);err!=nil{return nil,err};return ssh.ParsePrivateKey(encoded)}
