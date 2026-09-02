package docker

import (
	"bytes"
	"context"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/errdefs"
	"github.com/docker/docker/pkg/stdcopy"
	"nodexa/agent/internal/server"
)

const installLogName = ".nodexa-install.log"

func appendInstallLog(root, message string) { message=strings.TrimRight(message,"\r\n"); if message==""{return}; file,err:=os.OpenFile(filepath.Join(root,installLogName),os.O_CREATE|os.O_APPEND|os.O_WRONLY,0640);if err!=nil{return};defer file.Close();_,_=fmt.Fprintln(file,message) }
func resetInstallLog(root string){_=os.WriteFile(filepath.Join(root,installLogName),[]byte{},0640)}
func tailInstallText(text,tail string)string{count,err:=strconv.Atoi(tail);if err!=nil||count<=0{count=200};if count>2000{count=2000};lines:=strings.Split(strings.ReplaceAll(text,"\r\n","\n"),"\n");if len(lines)>count{lines=lines[len(lines)-count:]};return strings.TrimSpace(strings.Join(lines,"\n"))}
func(m *Manager)installerLogs(ctx context.Context,containerName,tail string)(string,error){reader,err:=m.cli.ContainerLogs(ctx,containerName,container.LogsOptions{ShowStdout:true,ShowStderr:true,Timestamps:false,Tail:tail});if err!=nil{return "",err};defer reader.Close();var output bytes.Buffer;if _,err:=stdcopy.StdCopy(&output,&output,reader);err!=nil{return "",err};return strings.TrimSpace(output.String()),nil}
func installCompleted(stored string)bool{return strings.Contains(stored,"Reinstall finished. Server is ready to start.")||strings.Contains(stored,"Managed template files are ready")}

func(m *Manager)InstallConsole(ctx context.Context,id,tail string)(string,bool,error){
	root,err:=m.serverRoot(id);if err!=nil{return "",false,err}
	stored:="";if body,readErr:=os.ReadFile(filepath.Join(root,installLogName));readErr==nil{stored=strings.TrimSpace(string(body))}
	installerName:="nx-install-"+id
	if inspect,inspectErr:=m.cli.ContainerInspect(ctx,installerName);inspectErr==nil{if inspect.State.Running{live,logsErr:=m.installerLogs(ctx,installerName,tail);if logsErr!=nil{return "",true,logsErr};combined:=strings.TrimSpace(strings.TrimSpace(stored)+"\n"+strings.TrimSpace(live));return tailInstallText(combined,tail),true,nil}}else if !errdefs.IsNotFound(inspectErr){return "",false,inspectErr}
	serverName:="nx-"+id
	if inspect,inspectErr:=m.cli.ContainerInspect(ctx,serverName);inspectErr==nil{if inspect.State.Running{return "",false,nil};startedAt:=strings.TrimSpace(inspect.State.StartedAt);if startedAt!=""&&startedAt!="0001-01-01T00:00:00Z"&&startedAt!="0001-01-01T00:00:00.000000000Z"{return "",false,nil};if stored!=""{if installCompleted(stored){return "Nodexa installation completed successfully.\nServer is ready to start. Press Start to launch it.",true,nil};return tailInstallText(stored,tail),true,nil};return "",false,nil}else if !errdefs.IsNotFound(inspectErr){return "",false,inspectErr}
	if stored!=""{return tailInstallText(stored,tail),true,nil};return "",false,nil
}

func(m *Manager)ReinstallWithProgress(ctx context.Context,r server.CreateRequest)error{
	root,err:=m.serverRoot(r.ID);if err!=nil{return err};if err:=os.MkdirAll(root,0750);err!=nil{return err}
	resetInstallLog(root);appendInstallLog(root,"container@nodexa~ Server marked as installing...");appendInstallLog(root,"[Nodexa Installer] Reinstall started")
	if err:=m.Reinstall(ctx,r);err!=nil{appendInstallLog(root,"[Nodexa Installer] REINSTALL FAILED: "+err.Error());return err}
	appendInstallLog(root,"[Nodexa Installer] Reinstall finished. Server is ready to start.");return nil
}

func installerError(exitCode int,captured string)error{detail:=tailInstallText(captured,"8");if detail==""{return fmt.Errorf("Minecraft installer exited with code %d",exitCode)};return fmt.Errorf("Minecraft installer exited with code %d: %s",exitCode,detail)}

func(m *Manager)installTemplateWithProgress(ctx context.Context,r server.CreateRequest,root string)error{
	template:=strings.ToLower(strings.TrimSpace(r.Template));if template!="minecraft"&&template!="minecraft-java"{return fmt.Errorf("unsupported installation template %q",r.Template)}
	// Use the same /home/container mount as the runtime container. This avoids
	// installer/runtime path drift and guarantees that the files visible in the
	// panel are exactly the files the installer modifies.
	installScript:=`set -eu
cd /home/container
VERSION="${MINECRAFT_VERSION:-1.21.8}"
PORT="${SERVER_PORT:-25565}"
PAPER_UA="Nodexa/0.13.7 (https://github.com/yupthatpandadk/Nodexa)"
PAPER_API="https://fill.papermc.io/v3/projects/paper/versions/${VERSION}/builds/latest"
echo "container@nodexa~ Server marked as installing..."
echo "[Nodexa Installer] [1/7] Preparing installation directory"
echo "[Nodexa Installer] Template: Minecraft Java / Paper"
echo "[Nodexa Installer] Minecraft version: ${VERSION}"
echo "[Nodexa Installer] [2/7] Resolving latest Paper build"
META="$(curl -fsSL -A "$PAPER_UA" "$PAPER_API")"
URL="$(printf '%s' "$META" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["downloads"]["server:default"]["url"])')"
[ -n "$URL" ] || { echo "[Nodexa Installer] ERROR: Paper API did not return a server download URL"; exit 1; }
echo "[Nodexa Installer] [3/7] Downloading Paper server"
# Download atomically so a failed reinstall never destroys the working jar.
curl -fL --retry 4 --retry-delay 2 -A "$PAPER_UA" "$URL" -o server.jar.nodexa-new
[ -s server.jar.nodexa-new ] || { rm -f server.jar.nodexa-new; echo "[Nodexa Installer] ERROR: downloaded server jar is missing or empty"; exit 1; }
mv -f server.jar.nodexa-new server.jar
echo "[Nodexa Installer] [4/7] Accepting Minecraft EULA"
[ -f eula.txt ] || printf 'eula=true\n' > eula.txt
if grep -q '^eula=' eula.txt; then sed -i 's/^eula=.*/eula=true/' eula.txt; else printf '\neula=true\n' >> eula.txt; fi
echo "[Nodexa Installer] [5/7] Updating server.properties"
# Preserve all user settings; only ensure the allocated runtime port is correct.
if [ ! -f server.properties ]; then printf 'server-port=%s\nquery.port=%s\n' "$PORT" "$PORT" > server.properties
else
 if grep -q '^server-port=' server.properties; then sed -i "s/^server-port=.*/server-port=${PORT}/" server.properties; else printf '\nserver-port=%s\n' "$PORT" >> server.properties; fi
 if grep -q '^query.port=' server.properties; then sed -i "s/^query.port=.*/query.port=${PORT}/" server.properties; else printf 'query.port=%s\n' "$PORT" >> server.properties; fi
fi
echo "[Nodexa Installer] [6/7] Verifying installation files"
ls -lh server.jar eula.txt server.properties
echo "[Nodexa Installer] [7/7] Minecraft installation completed successfully"
`
	installerName:="nx-install-"+r.ID
	if inspect,err:=m.cli.ContainerInspect(ctx,installerName);err==nil{if inspect.State.Running{t:=3;_=m.cli.ContainerStop(ctx,installerName,container.StopOptions{Timeout:&t})};_=m.cli.ContainerRemove(ctx,installerName,container.RemoveOptions{Force:true})}else if !errdefs.IsNotFound(err){return err}
	cfg:=&container.Config{Image:r.Image,Cmd:[]string{"/bin/sh","-lc",installScript},Env:env(r.Environment),WorkingDir:"/home/container",AttachStdout:true,AttachStderr:true}
	host:=&container.HostConfig{Binds:[]string{fmt.Sprintf("%s:/home/container",root)}}
	created,err:=m.cli.ContainerCreate(ctx,cfg,host,nil,nil,installerName);if err!=nil{return fmt.Errorf("create installer container: %w",err)}
	if err:=m.cli.ContainerStart(ctx,created.ID,container.StartOptions{});err!=nil{return fmt.Errorf("start installer container: %w",err)}
	waitCh,errCh:=m.cli.ContainerWait(ctx,created.ID,container.WaitConditionNotRunning);var exitCode int
	select{case result:=<-waitCh:exitCode=int(result.StatusCode);case waitErr:=<-errCh:if waitErr!=nil{return waitErr};case <-ctx.Done():return ctx.Err()}
	captured,logErr:=m.installerLogs(ctx,installerName,"2000");if logErr==nil&&captured!=""{for _,line:=range strings.Split(captured,"\n"){appendInstallLog(root,line)}}
	if exitCode!=0{return installerError(exitCode,captured)}
	if err:=os.WriteFile(filepath.Join(root,".nodexa-installed"),[]byte(time.Now().UTC().Format(time.RFC3339)+"\n"),0640);err!=nil{return err};return nil
}
