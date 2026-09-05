package dk.nodexa.app

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Backup
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.Cloud
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.Dns
import androidx.compose.material.icons.filled.Extension
import androidx.compose.material.icons.filled.Folder
import androidx.compose.material.icons.filled.FolderOpen
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material.icons.filled.Storage
import androidx.compose.material.icons.filled.Terminal
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URLEncoder
import java.net.URL
import java.nio.charset.StandardCharsets

private val Bg = Color(0xFF08111B)
private val Surface = Color(0xFF101A27)
private val Surface2 = Color(0xFF182536)
private val Accent = Color(0xFF7180FF)
private val Success = Color(0xFF46D987)
private val Danger = Color(0xFFFF708B)
private val Muted = Color(0xFF95A2B5)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { MaterialTheme { NodexaApp(applicationContext) } }
    }
}

data class ServerItem(val id: String, val name: String, val description: String, val status: String)
data class ServerMetrics(val state: String = "unknown", val cpu: Double = 0.0, val memoryBytes: Long = 0, val diskBytes: Long = 0, val uptimeMs: Long = 0)
data class SimpleItem(val title: String, val subtitle: String = "")
data class FileItem(val name: String, val isFile: Boolean, val size: Long = 0)
data class SocketCredentials(val socket: String, val token: String)

enum class ServerTab(val label: String) { DASHBOARD("Overview"), CONSOLE("Console"), FILES("Files"), MORE("More") }
enum class MoreSection(val label: String) { BACKUPS("Backups"), DATABASES("Databases"), PLUGINS("Plugins"), MODS("Mods") }

class ApiClient(private val baseUrl: String, private val token: String) {
    private fun normalizedBase() = baseUrl.trim().trimEnd('/')

    private fun request(path: String, method: String = "GET", body: String? = null): String {
        val connection = (URL(normalizedBase() + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 10000
            readTimeout = 20000
            setRequestProperty("Authorization", "Bearer $token")
            setRequestProperty("Accept", "Application/vnd.pterodactyl.v1+json")
            setRequestProperty("Content-Type", "application/json")
            if (body != null) {
                doOutput = true
                outputStream.bufferedWriter().use { it.write(body) }
            }
        }
        val code = connection.responseCode
        val text = (if (code in 200..299) connection.inputStream else connection.errorStream)?.bufferedReader()?.use { it.readText() }.orEmpty()
        if (code !in 200..299) throw IllegalStateException("HTTP $code: ${extractError(text)}")
        return text
    }

    private fun extractError(raw: String): String = try {
        val json = JSONObject(raw)
        json.optJSONArray("errors")?.optJSONObject(0)?.optString("detail")?.takeIf { it.isNotBlank() }
            ?: json.optString("message").takeIf { it.isNotBlank() }
            ?: "Ukendt API-fejl"
    } catch (_: Exception) {
        raw.take(240).ifBlank { "Ukendt API-fejl" }
    }

    fun servers(): List<ServerItem> {
        val data = JSONObject(request("/api/client")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(ServerItem(a.optString("identifier"), a.optString("name", "Server"), a.optString("description"), a.optString("status", "unknown")))
            }
        }
    }

    fun metrics(server: String): ServerMetrics {
        val root = JSONObject(request("/api/client/servers/$server/resources"))
        val a = root.optJSONObject("attributes") ?: root.optJSONObject("data")?.optJSONObject("attributes") ?: JSONObject()
        val resources = a.optJSONObject("resources") ?: JSONObject()
        return ServerMetrics(
            state = a.optString("current_state", "unknown"),
            cpu = resources.optDouble("cpu_absolute", 0.0),
            memoryBytes = resources.optLong("memory_bytes", 0L),
            diskBytes = resources.optLong("disk_bytes", 0L),
            uptimeMs = resources.optLong("uptime", 0L)
        )
    }

    fun power(server: String, signal: String) {
        request("/api/client/servers/$server/power", "POST", JSONObject().put("signal", signal).toString())
    }

    fun command(server: String, command: String) {
        request("/api/client/servers/$server/command", "POST", JSONObject().put("command", command).toString())
    }

    fun socketCredentials(server: String): SocketCredentials {
        val root = JSONObject(request("/api/client/servers/$server/websocket"))
        val data = root.optJSONObject("data") ?: root
        return SocketCredentials(data.optString("socket"), data.optString("token"))
    }

    fun files(server: String, directory: String): List<FileItem> {
        val encoded = URLEncoder.encode(directory, StandardCharsets.UTF_8.toString())
        val data = JSONObject(request("/api/client/servers/$server/files/list?directory=$encoded")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(FileItem(a.optString("name", "Ukendt"), a.optBoolean("is_file", false), a.optLong("size", 0)))
            }
        }.sortedWith(compareBy<FileItem> { it.isFile }.thenBy { it.name.lowercase() })
    }

    fun backups(server: String): List<SimpleItem> = listAttributes("/api/client/servers/$server/backups") { a ->
        val successful = a.optBoolean("is_successful", false)
        val completed = !a.isNull("completed_at")
        SimpleItem(a.optString("name", "Backup"), when { successful -> "Klar"; completed -> "Fejlet"; else -> "Behandler" })
    }

    fun createBackup(server: String) { request("/api/client/servers/$server/backups", "POST", "{}") }

    fun databases(server: String): List<SimpleItem> = listAttributes("/api/client/servers/$server/databases") { a ->
        val host = a.optJSONObject("host")
        val address = host?.optString("address").orEmpty()
        val port = host?.optInt("port", 0) ?: 0
        val hostText = if (address.isNotBlank() && address != "unavailable") {
            if (port > 0) "$address:$port" else address
        } else "Database host er ikke konfigureret"
        SimpleItem(a.optString("name", "Database"), hostText)
    }

    fun plugins(server: String): List<SimpleItem> = installedAddon(server, "plugins")
    fun mods(server: String): List<SimpleItem> = installedAddon(server, "mods")

    private fun listAttributes(path: String, mapper: (JSONObject) -> SimpleItem): List<SimpleItem> {
        val data = JSONObject(request(path)).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val wrapper = data.optJSONObject(i) ?: continue
                add(mapper(wrapper.optJSONObject("attributes") ?: wrapper))
            }
        }
    }

    private fun installedAddon(server: String, type: String): List<SimpleItem> {
        val data = JSONObject(request("/api/client/servers/$server/$type/installed")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val wrapper = data.optJSONObject(i) ?: continue
                val a = wrapper.optJSONObject("attributes") ?: wrapper
                add(SimpleItem(
                    a.optString("name", a.optString("filename", type)),
                    listOf(a.optString("version_number"), a.optString("version"), a.optString("filename")).filter { it.isNotBlank() }.distinct().joinToString(" · ")
                ))
            }
        }
    }

    companion object {
        fun formatBytes(bytes: Long): String = when {
            bytes >= 1024L * 1024 * 1024 -> String.format("%.2f GB", bytes / 1024.0 / 1024.0 / 1024.0)
            bytes >= 1024L * 1024 -> String.format("%.1f MB", bytes / 1024.0 / 1024.0)
            bytes >= 1024 -> String.format("%.0f KB", bytes / 1024.0)
            else -> "$bytes B"
        }

        fun formatUptime(ms: Long): String {
            val sec = ms / 1000
            val days = sec / 86400
            val hours = (sec % 86400) / 3600
            val minutes = (sec % 3600) / 60
            return if (days > 0) "${days}d ${hours}h" else "${hours}h ${minutes}m"
        }
    }
}

class ConsoleSocket(private val onLine: (String) -> Unit, private val onState: (String) -> Unit) {
    private val client = OkHttpClient()
    private var socket: WebSocket? = null

    fun connect(credentials: SocketCredentials) {
        close()
        socket = client.newWebSocket(Request.Builder().url(credentials.socket).build(), object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                webSocket.send(JSONObject().put("event", "auth").put("args", JSONArray().put(credentials.token)).toString())
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                runCatching {
                    val json = JSONObject(text)
                    val event = json.optString("event")
                    val args = json.optJSONArray("args")
                    when (event) {
                        "console output" -> args?.optString(0)?.takeIf { it.isNotBlank() }?.let(onLine)
                        "status" -> onState(args?.optString(0).orEmpty())
                        "auth success" -> onLine("Forbundet til live console.")
                        "token expiring", "token expired" -> onLine("Console-forbindelsen skal fornyes.")
                    }
                }
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                onLine("WebSocket-fejl: ${t.message ?: "ukendt fejl"}")
            }
        })
    }

    fun close() {
        socket?.close(1000, "closed")
        socket = null
    }
}

@Composable
fun NodexaApp(context: Context) {
    val prefs = remember { context.getSharedPreferences("nodexa", Context.MODE_PRIVATE) }
    var baseUrl by remember { mutableStateOf(prefs.getString("baseUrl", "") ?: "") }
    var token by remember { mutableStateOf(prefs.getString("token", "") ?: "") }
    var configured by remember { mutableStateOf(baseUrl.isNotBlank() && token.isNotBlank()) }
    var selected by remember { mutableStateOf<ServerItem?>(null) }

    when {
        !configured -> LoginScreen(baseUrl, token, { baseUrl = it }, { token = it }) {
            prefs.edit().putString("baseUrl", baseUrl.trim()).putString("token", token.trim()).apply()
            configured = true
        }
        selected == null -> ServerListScreen(ApiClient(baseUrl, token), { selected = it }) {
            prefs.edit().clear().apply(); configured = false; token = ""
        }
        else -> ServerScreen(ApiClient(baseUrl, token), selected!!) { selected = null }
    }
}

@Composable
fun LoginScreen(baseUrl: String, token: String, setBase: (String) -> Unit, setToken: (String) -> Unit, onSave: () -> Unit) {
    Box(Modifier.fillMaxSize().background(Bg).padding(24.dp), contentAlignment = Alignment.Center) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
            Box(Modifier.background(Accent, RoundedCornerShape(18.dp)).padding(horizontal = 19.dp, vertical = 12.dp)) {
                Text("N", color = Color.White, fontWeight = FontWeight.Black, style = MaterialTheme.typography.headlineMedium)
            }
            Spacer(Modifier.height(16.dp))
            Text("Nodexa", color = Color.White, style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
            Text("MOBILE SERVER CONTROL", color = Accent, style = MaterialTheme.typography.labelSmall)
            Spacer(Modifier.height(28.dp))
            Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(24.dp)) {
                Column(Modifier.padding(20.dp)) {
                    Text("Forbind panel", color = Color.White, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Text("Brug din Nodexa Client API key.", color = Muted, style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(14.dp))
                    OutlinedTextField(value = baseUrl, onValueChange = setBase, modifier = Modifier.fillMaxWidth(), label = { Text("Panel URL") }, placeholder = { Text("https://panel.example.com") })
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(value = token, onValueChange = setToken, modifier = Modifier.fillMaxWidth(), label = { Text("nxa_ API key") }, placeholder = { Text("nxa_...") }, visualTransformation = PasswordVisualTransformation())
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = onSave, enabled = baseUrl.startsWith("https://") && token.isNotBlank(), modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Accent)) { Text("Forbind") }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerListScreen(api: ApiClient, onOpen: (ServerItem) -> Unit, onLogout: () -> Unit) {
    val scope = rememberCoroutineScope()
    var servers by remember { mutableStateOf<List<ServerItem>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }

    fun load() {
        loading = true; error = null
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.servers() } }.onSuccess { servers = it }.onFailure { error = it.message ?: "Kunne ikke hente servere" }
            loading = false
        }
    }

    LaunchedEffect(Unit) { load() }

    Scaffold(containerColor = Bg, topBar = {
        TopAppBar(
            title = { Column { Text("Nodexa", color = Color.White, fontWeight = FontWeight.Bold); Text("Dine servere", color = Muted, style = MaterialTheme.typography.labelSmall) } },
            colors = TopAppBarDefaults.topAppBarColors(containerColor = Bg),
            actions = {
                IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Color.White) }
                IconButton(onClick = onLogout) { Icon(Icons.Default.Settings, null, tint = Color.White) }
            }
        )
    }) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).padding(horizontal = 16.dp)) {
            when {
                loading -> CircularProgressIndicator(Modifier.align(Alignment.Center), color = Accent)
                error != null -> Column(Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(error!!, color = Danger); Spacer(Modifier.height(12.dp)); Button(onClick = { load() }) { Text("Prøv igen") }
                }
                servers.isEmpty() -> Text("Ingen servere fundet.", color = Muted, modifier = Modifier.align(Alignment.Center))
                else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    item { Spacer(Modifier.height(4.dp)) }
                    items(servers) { server -> ServerCard(server) { onOpen(server) } }
                    item { Spacer(Modifier.height(16.dp)) }
                }
            }
        }
    }
}

@Composable
fun ServerCard(server: ServerItem, onClick: () -> Unit) {
    Card(onClick = onClick, colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.background(Accent.copy(alpha = .14f), RoundedCornerShape(14.dp)).padding(11.dp)) { Icon(Icons.Default.Dns, null, tint = Accent) }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(server.name, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
                Text(server.description.ifBlank { server.id }, color = Muted, style = MaterialTheme.typography.bodySmall)
            }
            StatusPill(server.status)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerScreen(api: ApiClient, server: ServerItem, onBack: () -> Unit) {
    var tab by remember { mutableStateOf(ServerTab.DASHBOARD) }
    var lastState by remember { mutableStateOf(server.status.ifBlank { "unknown" }) }

    Scaffold(
        containerColor = Bg,
        topBar = {
            TopAppBar(
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, null, tint = Color.White) } },
                title = { Column { Text(server.name, color = Color.White, fontWeight = FontWeight.Bold); Text("${server.id} · ${lastState.uppercase()}", color = if (lastState == "running") Success else Muted, style = MaterialTheme.typography.labelSmall) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface)
            )
        },
        bottomBar = {
            NavigationBar(containerColor = Surface) {
                NavigationBarItem(tab == ServerTab.DASHBOARD, { tab = ServerTab.DASHBOARD }, { Icon(Icons.Default.Dashboard, null) }, label = { Text("Overview") })
                NavigationBarItem(tab == ServerTab.CONSOLE, { tab = ServerTab.CONSOLE }, { Icon(Icons.Default.Terminal, null) }, label = { Text("Console") })
                NavigationBarItem(tab == ServerTab.FILES, { tab = ServerTab.FILES }, { Icon(Icons.Default.Folder, null) }, label = { Text("Files") })
                NavigationBarItem(tab == ServerTab.MORE, { tab = ServerTab.MORE }, { Icon(Icons.Default.MoreHoriz, null) }, label = { Text("More") })
            }
        }
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).background(Bg).padding(16.dp)) {
            when (tab) {
                ServerTab.DASHBOARD -> DashboardPanel(api, server) { lastState = it }
                ServerTab.CONSOLE -> ConsolePanel(api, server) { lastState = it }
                ServerTab.FILES -> FileBrowserPanel(api, server)
                ServerTab.MORE -> MorePanel(api, server)
            }
        }
    }
}

@Composable
fun DashboardPanel(api: ApiClient, server: ServerItem, onState: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    var metrics by remember { mutableStateOf(ServerMetrics()) }
    var loading by remember { mutableStateOf(true) }
    var message by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    suspend fun refresh() {
        runCatching { withContext(Dispatchers.IO) { api.metrics(server.id) } }.onSuccess { metrics = it; onState(it.state) }
        loading = false
    }

    fun signal(value: String) {
        busy = true; message = null
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.power(server.id, value) } }.onSuccess { message = "Kommando sendt"; delay(1200); refresh() }.onFailure { message = it.message }
            busy = false
        }
    }

    LaunchedEffect(server.id) { while (true) { refresh(); delay(8000) } }

    LazyColumn(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(24.dp), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp)) {
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("Server status", color = Muted)
                            Text(when (metrics.state) { "running" -> "Running"; "offline" -> "Offline"; "starting" -> "Starting"; "stopping" -> "Stopping"; else -> metrics.state.replaceFirstChar { it.uppercase() } }, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall)
                        }
                        StatusPill(metrics.state)
                    }
                    Spacer(Modifier.height(18.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(onClick = { signal("start") }, enabled = !busy, modifier = Modifier.weight(1f).height(52.dp), colors = ButtonDefaults.buttonColors(containerColor = Success)) {
                            Icon(Icons.Default.PlayArrow, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Start", maxLines = 1, fontSize = 12.sp)
                        }
                        Button(onClick = { signal("restart") }, enabled = !busy, modifier = Modifier.weight(1f).height(52.dp), colors = ButtonDefaults.buttonColors(containerColor = Accent)) {
                            Icon(Icons.Default.Refresh, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Restart", maxLines = 1, fontSize = 12.sp)
                        }
                        OutlinedButton(onClick = { signal("stop") }, enabled = !busy, modifier = Modifier.weight(1f).height(52.dp)) {
                            Icon(Icons.Default.Stop, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(4.dp)); Text("Stop", maxLines = 1, fontSize = 12.sp)
                        }
                    }
                    if (message != null) { Spacer(Modifier.height(10.dp)); Text(message!!, color = if (message!!.startsWith("HTTP")) Danger else Muted, style = MaterialTheme.typography.bodySmall) }
                }
            }
        }
        if (loading) item { LinearProgressIndicator(modifier = Modifier.fillMaxWidth(), color = Accent) }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) { MetricCard("CPU", String.format("%.1f%%", metrics.cpu), Modifier.weight(1f)); MetricCard("Memory", ApiClient.formatBytes(metrics.memoryBytes), Modifier.weight(1f)) } }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) { MetricCard("Disk", ApiClient.formatBytes(metrics.diskBytes), Modifier.weight(1f)); MetricCard("Uptime", ApiClient.formatUptime(metrics.uptimeMs), Modifier.weight(1f)) } }
    }
}

@Composable
fun MetricCard(title: String, value: String, modifier: Modifier = Modifier) {
    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(20.dp), modifier = modifier) {
        Column(Modifier.padding(16.dp)) { Text(title, color = Muted, fontWeight = FontWeight.SemiBold); Spacer(Modifier.height(8.dp)); Text(value, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleLarge) }
    }
}

@Composable
fun ConsolePanel(api: ApiClient, server: ServerItem, onState: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    val lines = remember { mutableStateListOf<String>() }
    var command by remember { mutableStateOf("") }
    var connecting by remember { mutableStateOf(true) }
    val consoleSocket = remember(server.id) { ConsoleSocket(
        onLine = { line -> if (lines.size >= 250) lines.removeAt(0); lines.add(line) },
        onState = onState
    ) }

    fun connect() {
        connecting = true
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.socketCredentials(server.id) } }.onSuccess { consoleSocket.connect(it); connecting = false }.onFailure { lines.add("Kunne ikke forbinde til live console: ${it.message}"); connecting = false }
        }
    }

    LaunchedEffect(server.id) { connect() }
    DisposableEffect(server.id) { onDispose { consoleSocket.close() } }

    Column(Modifier.fillMaxSize()) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.Terminal, null, tint = Accent); Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) { Text("Console", color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall); Text("Live output fra serveren", color = Muted) }
            IconButton(onClick = { connect() }) { Icon(Icons.Default.Refresh, null, tint = Accent) }
        }
        Spacer(Modifier.height(14.dp))
        Card(colors = CardDefaults.cardColors(containerColor = Color(0xFF050B11)), shape = RoundedCornerShape(20.dp), modifier = Modifier.fillMaxWidth().weight(1f)) {
            LazyColumn(modifier = Modifier.fillMaxSize().padding(14.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                if (connecting) item { Text("Forbinder til live console…", color = Muted, fontFamily = FontFamily.Monospace) }
                if (lines.isEmpty() && !connecting) item { Text("Venter på console-output…", color = Muted, fontFamily = FontFamily.Monospace) }
                items(lines) { line -> Text(line, color = Color(0xFFD6DEE8), fontFamily = FontFamily.Monospace, fontSize = 12.sp) }
            }
        }
        Spacer(Modifier.height(12.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(value = command, onValueChange = { command = it }, modifier = Modifier.weight(1f), singleLine = true, placeholder = { Text("Skriv kommando…") })
            Spacer(Modifier.width(8.dp))
            IconButton(onClick = {
                val value = command.trim()
                if (value.isNotBlank()) scope.launch { runCatching { withContext(Dispatchers.IO) { api.command(server.id, value) } }.onSuccess { lines.add("> $value"); command = "" }.onFailure { lines.add("Fejl: ${it.message}") } }
            }) { Icon(Icons.Default.Send, null, tint = Accent, modifier = Modifier.size(32.dp)) }
        }
    }
}

@Composable
fun FileBrowserPanel(api: ApiClient, server: ServerItem) {
    val scope = rememberCoroutineScope()
    var directory by remember { mutableStateOf("/") }
    var data by remember { mutableStateOf<List<FileItem>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }

    fun load() {
        loading = true; error = null
        scope.launch { runCatching { withContext(Dispatchers.IO) { api.files(server.id, directory) } }.onSuccess { data = it }.onFailure { error = it.message }; loading = false }
    }

    LaunchedEffect(directory, server.id) { load() }

    Column(Modifier.fillMaxSize()) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            if (directory != "/") IconButton(onClick = { directory = directory.trimEnd('/').substringBeforeLast('/', "").ifBlank { "/" } }) { Icon(Icons.Default.ArrowBack, null, tint = Color.White) }
            Column(Modifier.weight(1f)) { Text("Files", color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall); Text(directory, color = Muted, style = MaterialTheme.typography.bodySmall) }
            IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Accent) }
        }
        Spacer(Modifier.height(10.dp))
        when {
            loading -> CircularProgressIndicator(Modifier.align(Alignment.CenterHorizontally), color = Accent)
            error != null -> Text(error!!, color = Danger)
            data.isEmpty() -> EmptyState("Mappen er tom.")
            else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(data) { item ->
                    Card(colors = CardDefaults.cardColors(containerColor = Surface2), shape = RoundedCornerShape(18.dp), modifier = Modifier.fillMaxWidth().clickable(enabled = !item.isFile) {
                        val base = if (directory == "/") "" else directory.trimEnd('/')
                        directory = "$base/${item.name}"
                    }) {
                        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(if (item.isFile) Icons.Default.Cloud else Icons.Default.FolderOpen, null, tint = Accent); Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) { Text(item.name, color = Color.White, fontWeight = FontWeight.SemiBold); Text(if (item.isFile) ApiClient.formatBytes(item.size) else "Mappe", color = Muted, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun MorePanel(api: ApiClient, server: ServerItem) {
    var section by remember { mutableStateOf<MoreSection?>(null) }
    if (section == null) {
        Column(Modifier.fillMaxSize()) {
            Text("More", color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall); Text("Serverværktøjer", color = Muted); Spacer(Modifier.height(16.dp))
            MoreCard("Backups", "Opret og administrér backups", Icons.Default.Backup) { section = MoreSection.BACKUPS }; Spacer(Modifier.height(10.dp))
            MoreCard("Databases", "Administrér serverens databaser", Icons.Default.Storage) { section = MoreSection.DATABASES }; Spacer(Modifier.height(10.dp))
            MoreCard("Plugins", "Installerede Paper/Spigot plugins", Icons.Default.Extension) { section = MoreSection.PLUGINS }; Spacer(Modifier.height(10.dp))
            MoreCard("Mods", "Forge/Fabric mods", Icons.Default.Build) { section = MoreSection.MODS }
        }
    } else MoreSectionPanel(api, server, section!!) { section = null }
}

@Composable
fun MoreCard(title: String, subtitle: String, icon: ImageVector, onClick: () -> Unit) {
    Card(onClick = onClick, colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(20.dp), modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.background(Accent.copy(alpha = .14f), RoundedCornerShape(14.dp)).padding(10.dp)) { Icon(icon, null, tint = Accent) }
            Spacer(Modifier.width(12.dp)); Column { Text(title, color = Color.White, fontWeight = FontWeight.Bold); Text(subtitle, color = Muted, style = MaterialTheme.typography.bodySmall) }
        }
    }
}

@Composable
fun MoreSectionPanel(api: ApiClient, server: ServerItem, section: MoreSection, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    var data by remember(section, server.id) { mutableStateOf<List<SimpleItem>>(emptyList()) }
    var loading by remember(section, server.id) { mutableStateOf(true) }
    var error by remember(section, server.id) { mutableStateOf<String?>(null) }
    var actionBusy by remember { mutableStateOf(false) }

    fun load() {
        loading = true; error = null
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { when (section) { MoreSection.BACKUPS -> api.backups(server.id); MoreSection.DATABASES -> api.databases(server.id); MoreSection.PLUGINS -> api.plugins(server.id); MoreSection.MODS -> api.mods(server.id) } } }
                .onSuccess { data = it }
                .onFailure { throwable ->
                    val msg = throwable.message.orEmpty()
                    error = when {
                        section == MoreSection.MODS && msg.contains("422") -> "Mods er ikke tilgængelige på denne servertype. Mod Manager understøtter Forge og Fabric."
                        section == MoreSection.DATABASES && msg.contains("500") -> "Databaser kunne ikke hentes. Nodexa har registreret en database-konfigurationsfejl på panelet."
                        else -> msg.ifBlank { "Kunne ikke hente data." }
                    }
                }
            loading = false
        }
    }

    LaunchedEffect(section, server.id) { load() }

    Column(Modifier.fillMaxSize()) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, null, tint = Color.White) }
            Text(section.label, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall, modifier = Modifier.weight(1f))
            IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Accent) }
        }
        if (section == MoreSection.BACKUPS) {
            Spacer(Modifier.height(4.dp))
            Button(onClick = {
                actionBusy = true
                scope.launch { runCatching { withContext(Dispatchers.IO) { api.createBackup(server.id) } }.onSuccess { delay(800); load() }.onFailure { error = it.message }; actionBusy = false }
            }, enabled = !actionBusy, modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Accent)) {
                Icon(Icons.Default.Backup, null); Spacer(Modifier.width(8.dp)); Text(if (actionBusy) "Opretter backup…" else "Opret backup")
            }
        }
        Spacer(Modifier.height(12.dp))
        when {
            loading -> CircularProgressIndicator(Modifier.align(Alignment.CenterHorizontally), color = Accent)
            error != null -> Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(20.dp), modifier = Modifier.fillMaxWidth()) { Text(error!!, color = if (section == MoreSection.MODS) Muted else Danger, modifier = Modifier.padding(18.dp)) }
            data.isEmpty() -> EmptyState(when (section) { MoreSection.BACKUPS -> "Der er ingen backups endnu."; MoreSection.DATABASES -> "Der er ingen databaser på serveren."; MoreSection.PLUGINS -> "Ingen plugins blev fundet."; MoreSection.MODS -> "Ingen mods blev fundet." })
            else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(data) { item -> Card(colors = CardDefaults.cardColors(containerColor = Surface2), shape = RoundedCornerShape(18.dp), modifier = Modifier.fillMaxWidth()) { Column(Modifier.padding(16.dp)) { Text(item.title, color = Color.White, fontWeight = FontWeight.SemiBold); if (item.subtitle.isNotBlank()) { Spacer(Modifier.height(3.dp)); Text(item.subtitle, color = Muted, style = MaterialTheme.typography.bodySmall) } } } }
            }
        }
    }
}

@Composable
fun EmptyState(text: String) {
    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(20.dp), modifier = Modifier.fillMaxWidth()) { Text(text, color = Muted, modifier = Modifier.padding(18.dp)) }
}

@Composable
fun StatusPill(status: String) {
    val online = status.equals("running", true) || status.equals("online", true)
    val color = if (online) Success else Muted
    Box(Modifier.background(color.copy(alpha = .14f), RoundedCornerShape(30.dp)).padding(horizontal = 12.dp, vertical = 7.dp)) {
        Text(if (online) "ONLINE" else status.ifBlank { "UNKNOWN" }.uppercase(), color = color, style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
    }
}
