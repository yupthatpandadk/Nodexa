package dk.nodexa.app

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
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

private val Bg = Color(0xFF090D14)
private val CardBg = Color(0xFF121824)
private val CardAlt = Color(0xFF171F2C)
private val Accent = Color(0xFF7C6CFF)
private val AccentSoft = Color(0xFF201D42)
private val Text = Color(0xFFF5F7FB)
private val Muted = Color(0xFF8C97A8)
private val Success = Color(0xFF46D98A)
private val Danger = Color(0xFFFF637E)
private val Warning = Color(0xFFFFB84D)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { NodexaTheme { NodexaApp(applicationContext) } }
    }
}

@Composable
fun NodexaTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = darkColorScheme(
            primary = Accent,
            secondary = Accent,
            background = Bg,
            surface = CardBg,
            onPrimary = Color.White,
            onBackground = Text,
            onSurface = Text
        ),
        content = content
    )
}

data class Server(val id: String, val name: String, val description: String)
data class Metrics(val state: String = "unknown", val cpu: Double = 0.0, val memory: Long = 0, val disk: Long = 0, val uptime: Long = 0)
data class FileEntry(val name: String, val isFile: Boolean, val size: Long)
data class ItemRow(val title: String, val subtitle: String = "")
data class SocketInfo(val socket: String, val token: String)

enum class MainTab(val title: String, val icon: ImageVector) {
    OVERVIEW("Overview", Icons.Default.Dashboard),
    CONSOLE("Console", Icons.Default.Terminal),
    FILES("Files", Icons.Default.Folder),
    MORE("More", Icons.Default.GridView)
}

enum class MorePage(val title: String, val icon: ImageVector) {
    BACKUPS("Backups", Icons.Default.Backup),
    DATABASES("Databases", Icons.Default.Storage),
    SCHEDULES("Schedules", Icons.Default.Schedule),
    NETWORK("Network", Icons.Default.Lan),
    STARTUP("Startup", Icons.Default.SettingsSuggest),
    USERS("Users", Icons.Default.People),
    SETTINGS("Settings", Icons.Default.Settings)
}

class Api(private val base: String, private val token: String) {
    private fun root() = base.trim().trimEnd('/')

    private fun call(path: String, method: String = "GET", body: String? = null): String {
        val c = URL(root() + path).openConnection() as HttpURLConnection
        c.requestMethod = method
        c.connectTimeout = 12000
        c.readTimeout = 25000
        c.setRequestProperty("Authorization", "Bearer ${token.trim()}")
        c.setRequestProperty("Accept", "Application/vnd.pterodactyl.v1+json")
        c.setRequestProperty("Content-Type", "application/json")
        if (body != null) {
            c.doOutput = true
            c.outputStream.bufferedWriter().use { it.write(body) }
        }
        val code = c.responseCode
        val text = (if (code in 200..299) c.inputStream else c.errorStream)?.bufferedReader()?.use { it.readText() }.orEmpty()
        if (code !in 200..299) throw IllegalStateException("HTTP $code · ${errorText(text)}")
        return text
    }

    private fun errorText(raw: String): String = runCatching {
        val j = JSONObject(raw)
        j.optJSONArray("errors")?.optJSONObject(0)?.optString("detail")
            ?.takeIf { it.isNotBlank() }
            ?: j.optString("message").ifBlank { "Ukendt API-fejl" }
    }.getOrElse { raw.take(160).ifBlank { "Ukendt API-fejl" } }

    fun servers(): List<Server> {
        val data = JSONObject(call("/api/client")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(Server(a.optString("identifier"), a.optString("name", "Server"), a.optString("description")))
            }
        }
    }

    fun metrics(id: String): Metrics {
        val root = JSONObject(call("/api/client/servers/$id/resources"))
        val a = root.optJSONObject("attributes") ?: root.optJSONObject("data")?.optJSONObject("attributes") ?: JSONObject()
        val r = a.optJSONObject("resources") ?: JSONObject()
        return Metrics(a.optString("current_state", "unknown"), r.optDouble("cpu_absolute", 0.0), r.optLong("memory_bytes"), r.optLong("disk_bytes"), r.optLong("uptime"))
    }

    fun power(id: String, signal: String) = call("/api/client/servers/$id/power", "POST", JSONObject().put("signal", signal).toString())
    fun command(id: String, command: String) = call("/api/client/servers/$id/command", "POST", JSONObject().put("command", command).toString())

    fun socket(id: String): SocketInfo {
        val root = JSONObject(call("/api/client/servers/$id/websocket"))
        val d = root.optJSONObject("data") ?: root
        return SocketInfo(d.optString("socket"), d.optString("token"))
    }

    fun files(id: String, dir: String): List<FileEntry> {
        val encoded = URLEncoder.encode(dir, StandardCharsets.UTF_8.toString())
        val data = JSONObject(call("/api/client/servers/$id/files/list?directory=$encoded")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(FileEntry(a.optString("name"), a.optBoolean("is_file"), a.optLong("size")))
            }
        }.sortedWith(compareBy<FileEntry> { it.isFile }.thenBy { it.name.lowercase() })
    }

    fun list(path: String, mapper: (JSONObject) -> ItemRow): List<ItemRow> {
        val data = JSONObject(call(path)).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val wrapper = data.optJSONObject(i) ?: continue
                add(mapper(wrapper.optJSONObject("attributes") ?: wrapper))
            }
        }
    }

    fun backups(id: String) = list("/api/client/servers/$id/backups") { a ->
        ItemRow(a.optString("name", "Backup"), when {
            a.optBoolean("is_successful") -> "Completed"
            !a.isNull("completed_at") -> "Failed"
            else -> "Processing"
        })
    }

    fun createBackup(id: String) = call("/api/client/servers/$id/backups", "POST", "{}")

    fun databases(id: String) = list("/api/client/servers/$id/databases") { a ->
        val h = a.optJSONObject("host")
        val host = h?.optString("address").orEmpty()
        val port = h?.optInt("port", 0) ?: 0
        ItemRow(a.optString("name", "Database"), if (host.isBlank()) "Host unavailable" else if (port > 0) "$host:$port" else host)
    }

    fun schedules(id: String) = list("/api/client/servers/$id/schedules") { a ->
        ItemRow(a.optString("name", "Schedule"), if (a.optBoolean("is_active")) "Active" else "Disabled")
    }

    fun network(id: String) = list("/api/client/servers/$id/network/allocations") { a ->
        val ip = a.optString("ip", "0.0.0.0")
        val port = a.optInt("port")
        ItemRow("$ip:$port", if (a.optBoolean("is_default")) "Primary allocation" else "Allocation")
    }

    fun users(id: String) = list("/api/client/servers/$id/users") { a ->
        ItemRow(a.optString("email", "User"), a.optString("username"))
    }

    fun startup(id: String) = list("/api/client/servers/$id/startup") { a ->
        ItemRow(a.optString("name", a.optString("env_variable", "Variable")), a.optString("server_value", a.optString("default_value")))
    }

    companion object {
        fun bytes(v: Long): String = when {
            v >= 1024L * 1024 * 1024 -> String.format("%.2f GB", v / 1024.0 / 1024.0 / 1024.0)
            v >= 1024L * 1024 -> String.format("%.1f MB", v / 1024.0 / 1024.0)
            v >= 1024 -> String.format("%.0f KB", v / 1024.0)
            else -> "$v B"
        }

        fun uptime(ms: Long): String {
            val s = ms / 1000
            val d = s / 86400
            val h = (s % 86400) / 3600
            val m = (s % 3600) / 60
            return if (d > 0) "${d}d ${h}h" else "${h}h ${m}m"
        }
    }
}

class LiveConsole(private val line: (String) -> Unit, private val state: (String) -> Unit) {
    private val client = OkHttpClient()
    private var socket: WebSocket? = null

    fun connect(info: SocketInfo) {
        close()
        socket = client.newWebSocket(Request.Builder().url(info.socket).build(), object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                webSocket.send(JSONObject().put("event", "auth").put("args", JSONArray().put(info.token)).toString())
            }
            override fun onMessage(webSocket: WebSocket, text: String) {
                runCatching {
                    val j = JSONObject(text)
                    val event = j.optString("event")
                    val args = j.optJSONArray("args")
                    when (event) {
                        "console output" -> args?.optString(0)?.let(line)
                        "status" -> state(args?.optString(0).orEmpty())
                        "auth success" -> line("Connected to live console")
                        "token expiring", "token expired" -> line("Console token is expiring")
                        else -> Unit
                    }
                }
            }
            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                line("WebSocket error: ${t.message ?: "unknown"}")
            }
        })
    }

    fun close() { socket?.close(1000, "bye"); socket = null }
}

@Composable
fun NodexaApp(context: Context) {
    val prefs = remember { context.getSharedPreferences("nodexa", Context.MODE_PRIVATE) }
    var base by remember { mutableStateOf(prefs.getString("base", "") ?: "") }
    var token by remember { mutableStateOf(prefs.getString("token", "") ?: "") }
    var loggedIn by remember { mutableStateOf(base.isNotBlank() && token.isNotBlank()) }
    var selected by remember { mutableStateOf<Server?>(null) }

    when {
        !loggedIn -> LoginScreen(base, token, { base = it }, { token = it }) {
            prefs.edit().putString("base", base.trim()).putString("token", token.trim()).apply()
            loggedIn = true
        }
        selected == null -> ServerList(Api(base, token), onSelect = { selected = it }) {
            prefs.edit().clear().apply(); token = ""; loggedIn = false
        }
        else -> ServerShell(Api(base, token), selected!!, onBack = { selected = null })
    }
}

@Composable
fun LoginScreen(base: String, token: String, setBase: (String) -> Unit, setToken: (String) -> Unit, save: () -> Unit) {
    Box(Modifier.fillMaxSize().background(Bg).padding(24.dp), contentAlignment = Alignment.Center) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
            Box(Modifier.size(66.dp).background(Accent, RoundedCornerShape(18.dp)), contentAlignment = Alignment.Center) {
                Text("N", color = Color.White, fontWeight = FontWeight.Black, fontSize = 32.sp)
            }
            Spacer(Modifier.height(18.dp))
            Text("Nodexa", color = Text, fontSize = 30.sp, fontWeight = FontWeight.Bold)
            Text("Server management in your pocket", color = Muted, fontSize = 13.sp)
            Spacer(Modifier.height(28.dp))
            Card(colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(24.dp)) {
                Column(Modifier.padding(20.dp)) {
                    Text("Connect panel", color = Text, fontSize = 19.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(6.dp))
                    Text("Enter your Nodexa panel URL and Client API key.", color = Muted, fontSize = 13.sp)
                    Spacer(Modifier.height(18.dp))
                    OutlinedTextField(base, setBase, Modifier.fillMaxWidth(), label = { Text("Panel URL") }, placeholder = { Text("https://panel.example.com") }, singleLine = true)
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(token, setToken, Modifier.fillMaxWidth(), label = { Text("Client API key") }, placeholder = { Text("nxa_...") }, visualTransformation = PasswordVisualTransformation(), singleLine = true)
                    Spacer(Modifier.height(18.dp))
                    Button(save, enabled = base.startsWith("http") && token.isNotBlank(), modifier = Modifier.fillMaxWidth().height(50.dp), shape = RoundedCornerShape(14.dp)) { Text("Connect") }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerList(api: Api, onSelect: (Server) -> Unit, logout: () -> Unit) {
    var servers by remember { mutableStateOf<List<Server>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun refresh() = scope.launch {
        loading = true; error = null
        runCatching { withContext(Dispatchers.IO) { api.servers() } }
            .onSuccess { servers = it }
            .onFailure { error = it.message }
        loading = false
    }
    LaunchedEffect(Unit) { refresh() }

    Scaffold(
        containerColor = Bg,
        topBar = {
            TopAppBar(
                title = { Column { Text("Servers", fontWeight = FontWeight.Bold); Text("Nodexa", color = Muted, fontSize = 12.sp) } },
                actions = { IconButton(onClick = { refresh() }) { Icon(Icons.Default.Refresh, null) }; IconButton(onClick = logout) { Icon(Icons.Default.Logout, null) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Bg)
            )
        }
    ) { pad ->
        Box(Modifier.padding(pad).fillMaxSize()) {
            when {
                loading -> CircularProgressIndicator(Modifier.align(Alignment.Center))
                error != null -> ErrorBox(error!!, Modifier.align(Alignment.Center).padding(24.dp))
                else -> LazyColumn(Modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(bottom = 24.dp)) {
                    items(servers) { s -> ServerCard(s, api, onSelect) }
                }
            }
        }
    }
}

@Composable
fun ServerCard(server: Server, api: Api, onSelect: (Server) -> Unit) {
    var metrics by remember { mutableStateOf(Metrics()) }
    LaunchedEffect(server.id) { runCatching { withContext(Dispatchers.IO) { api.metrics(server.id) } }.onSuccess { metrics = it } }
    val online = metrics.state == "running"
    Card(
        Modifier.fillMaxWidth().clickable { onSelect(server) },
        colors = CardDefaults.cardColors(containerColor = CardBg),
        shape = RoundedCornerShape(20.dp)
    ) {
        Column(Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(44.dp).background(AccentSoft, RoundedCornerShape(14.dp)), contentAlignment = Alignment.Center) { Icon(Icons.Default.Dns, null, tint = Accent) }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) { Text(server.name, color = Text, fontWeight = FontWeight.SemiBold, fontSize = 17.sp); if (server.description.isNotBlank()) Text(server.description, color = Muted, fontSize = 12.sp, maxLines = 1) }
                StatusPill(metrics.state)
            }
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TinyStat("CPU", "${metrics.cpu.toInt()}%", Modifier.weight(1f))
                TinyStat("RAM", Api.bytes(metrics.memory), Modifier.weight(1f))
                TinyStat("UPTIME", if (online) Api.uptime(metrics.uptime) else "—", Modifier.weight(1f))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerShell(api: Api, server: Server, onBack: () -> Unit) {
    var tab by remember { mutableStateOf(MainTab.OVERVIEW) }
    Scaffold(
        containerColor = Bg,
        topBar = {
            TopAppBar(
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, null) } },
                title = { Column { Text(server.name, fontWeight = FontWeight.Bold, fontSize = 18.sp); Text(server.id, color = Muted, fontSize = 11.sp) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Bg)
            )
        },
        bottomBar = {
            NavigationBar(containerColor = CardBg) {
                MainTab.entries.forEach { item ->
                    NavigationBarItem(selected = tab == item, onClick = { tab = item }, icon = { Icon(item.icon, null) }, label = { Text(item.title, fontSize = 11.sp) })
                }
            }
        }
    ) { pad ->
        Box(Modifier.padding(pad).fillMaxSize()) {
            when (tab) {
                MainTab.OVERVIEW -> Overview(api, server)
                MainTab.CONSOLE -> ConsolePage(api, server)
                MainTab.FILES -> FilesPage(api, server)
                MainTab.MORE -> MoreMenu(api, server)
            }
        }
    }
}

@Composable
fun Overview(api: Api, server: Server) {
    var m by remember { mutableStateOf(Metrics()) }
    var err by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    suspend fun load() { runCatching { withContext(Dispatchers.IO) { api.metrics(server.id) } }.onSuccess { m = it; err = null }.onFailure { err = it.message } }
    LaunchedEffect(server.id) { while (true) { load(); delay(5000) } }

    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(bottom = 24.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(20.dp)) {
                Column(Modifier.padding(18.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) { Text("Server status", color = Muted, modifier = Modifier.weight(1f)); StatusPill(m.state) }
                    Spacer(Modifier.height(14.dp))
                    Text(server.name, color = Text, fontWeight = FontWeight.Bold, fontSize = 22.sp)
                    Text(if (m.state == "running") "Your server is online and reachable." else "Current state: ${m.state}", color = Muted, fontSize = 13.sp)
                }
            }
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                MetricCard("CPU", "${String.format("%.1f", m.cpu)}%", Icons.Default.Memory, Modifier.weight(1f))
                MetricCard("Memory", Api.bytes(m.memory), Icons.Default.Storage, Modifier.weight(1f))
            }
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                MetricCard("Disk", Api.bytes(m.disk), Icons.Default.HardDrive, Modifier.weight(1f))
                MetricCard("Uptime", Api.uptime(m.uptime), Icons.Default.Timer, Modifier.weight(1f))
            }
        }
        item {
            Text("Power controls", color = Text, fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                PowerButton("Start", Icons.Default.PlayArrow, Success, Modifier.weight(1f)) { scope.launch { withContext(Dispatchers.IO) { api.power(server.id, "start") }; load() } }
                PowerButton("Restart", Icons.Default.Refresh, Warning, Modifier.weight(1f)) { scope.launch { withContext(Dispatchers.IO) { api.power(server.id, "restart") }; load() } }
                PowerButton("Stop", Icons.Default.Stop, Danger, Modifier.weight(1f)) { scope.launch { withContext(Dispatchers.IO) { api.power(server.id, "stop") }; load() } }
            }
        }
        err?.let { item { ErrorBox(it) } }
    }
}

@Composable
fun ConsolePage(api: Api, server: Server) {
    val lines = remember { mutableStateListOf<String>() }
    var command by remember { mutableStateOf("") }
    var state by remember { mutableStateOf("connecting") }
    val scope = rememberCoroutineScope()
    val socket = remember(server.id) { LiveConsole({ text -> if (lines.size > 500) lines.removeAt(0); lines.add(text) }, { state = it }) }

    LaunchedEffect(server.id) {
        runCatching { withContext(Dispatchers.IO) { api.socket(server.id) } }
            .onSuccess { socket.connect(it) }
            .onFailure { lines.add(it.message ?: "Failed to connect") }
    }
    DisposableEffect(server.id) { onDispose { socket.close() } }

    Column(Modifier.fillMaxSize().padding(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) { Text("Live console", color = Text, fontWeight = FontWeight.SemiBold); Spacer(Modifier.weight(1f)); StatusPill(state) }
        Spacer(Modifier.height(10.dp))
        Card(Modifier.weight(1f).fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Color(0xFF070A10)), shape = RoundedCornerShape(16.dp)) {
            LazyColumn(Modifier.fillMaxSize().padding(12.dp)) { items(lines) { line -> Text(line, color = Color(0xFFD2D8E2), fontFamily = FontFamily.Monospace, fontSize = 11.sp, lineHeight = 15.sp) } }
        }
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(command, { command = it }, Modifier.weight(1f), placeholder = { Text("Enter command…") }, singleLine = true)
            Spacer(Modifier.width(8.dp))
            FilledIconButton(onClick = {
                val cmd = command.trim(); if (cmd.isNotEmpty()) { command = ""; scope.launch { runCatching { withContext(Dispatchers.IO) { api.command(server.id, cmd) } }.onFailure { lines.add(it.message ?: "Command failed") } } }
            }, colors = IconButtonDefaults.filledIconButtonColors(containerColor = Accent)) { Icon(Icons.Default.Send, null) }
        }
    }
}

@Composable
fun FilesPage(api: Api, server: Server) {
    var dir by remember { mutableStateOf("/") }
    var entries by remember { mutableStateOf<List<FileEntry>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun load() = scope.launch {
        loading = true; error = null
        runCatching { withContext(Dispatchers.IO) { api.files(server.id, dir) } }.onSuccess { entries = it }.onFailure { error = it.message }
        loading = false
    }
    LaunchedEffect(dir) { load() }

    Column(Modifier.fillMaxSize().padding(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (dir != "/") IconButton(onClick = { dir = dir.trimEnd('/').substringBeforeLast('/', "").let { if (it.isBlank()) "/" else "$it/" } }) { Icon(Icons.Default.ArrowBack, null) }
            Column(Modifier.weight(1f)) { Text("Files", color = Text, fontWeight = FontWeight.SemiBold); Text(dir, color = Muted, fontSize = 11.sp) }
            IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null) }
        }
        when {
            loading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            error != null -> ErrorBox(error!!)
            else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(bottom = 20.dp)) {
                items(entries) { f ->
                    Card(Modifier.fillMaxWidth().clickable(enabled = !f.isFile) { dir = if (dir == "/") "/${f.name}/" else "$dir${f.name}/" }, colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(14.dp)) {
                        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(if (f.isFile) Icons.Default.Description else Icons.Default.Folder, null, tint = if (f.isFile) Muted else Accent)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) { Text(f.name, color = Text, fontWeight = FontWeight.Medium); Text(if (f.isFile) Api.bytes(f.size) else "Directory", color = Muted, fontSize = 11.sp) }
                            Icon(Icons.Default.ChevronRight, null, tint = Muted)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun MoreMenu(api: Api, server: Server) {
    var page by remember { mutableStateOf<MorePage?>(null) }
    if (page != null) { GenericPage(api, server, page!!) { page = null }; return }

    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp), contentPadding = PaddingValues(bottom = 24.dp)) {
        item { Text("Server management", color = Text, fontWeight = FontWeight.Bold, fontSize = 21.sp); Text("Everything else for this server", color = Muted, fontSize = 13.sp); Spacer(Modifier.height(6.dp)) }
        items(MorePage.entries) { p ->
            Card(Modifier.fillMaxWidth().clickable { page = p }, colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(16.dp)) {
                Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(42.dp).background(AccentSoft, RoundedCornerShape(12.dp)), contentAlignment = Alignment.Center) { Icon(p.icon, null, tint = Accent) }
                    Spacer(Modifier.width(12.dp)); Text(p.title, color = Text, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f)); Icon(Icons.Default.ChevronRight, null, tint = Muted)
                }
            }
        }
    }
}

@Composable
fun GenericPage(api: Api, server: Server, page: MorePage, back: () -> Unit) {
    var rows by remember { mutableStateOf<List<ItemRow>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun load() = scope.launch {
        loading = true; error = null
        runCatching {
            withContext(Dispatchers.IO) {
                when (page) {
                    MorePage.BACKUPS -> api.backups(server.id)
                    MorePage.DATABASES -> api.databases(server.id)
                    MorePage.SCHEDULES -> api.schedules(server.id)
                    MorePage.NETWORK -> api.network(server.id)
                    MorePage.USERS -> api.users(server.id)
                    MorePage.STARTUP -> api.startup(server.id)
                    MorePage.SETTINGS -> listOf(ItemRow("Server identifier", server.id), ItemRow("Server name", server.name))
                }
            }
        }.onSuccess { rows = it }.onFailure { error = it.message }
        loading = false
    }
    LaunchedEffect(page) { load() }

    Column(Modifier.fillMaxSize().padding(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = back) { Icon(Icons.Default.ArrowBack, null) }
            Text(page.title, color = Text, fontWeight = FontWeight.Bold, fontSize = 20.sp, modifier = Modifier.weight(1f))
            if (page == MorePage.BACKUPS) FilledIconButton(onClick = { scope.launch { runCatching { withContext(Dispatchers.IO) { api.createBackup(server.id) } }; load() } }, colors = IconButtonDefaults.filledIconButtonColors(containerColor = Accent)) { Icon(Icons.Default.Add, null) }
        }
        Spacer(Modifier.height(8.dp))
        when {
            loading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            error != null -> ErrorBox(error!!)
            rows.isEmpty() -> EmptyState("No ${page.title.lowercase()} found")
            else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(bottom = 20.dp)) {
                items(rows) { row ->
                    Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(14.dp)) {
                        Column(Modifier.padding(15.dp)) { Text(row.title, color = Text, fontWeight = FontWeight.Medium); if (row.subtitle.isNotBlank()) Text(row.subtitle, color = Muted, fontSize = 12.sp) }
                    }
                }
            }
        }
    }
}

@Composable
fun TinyStat(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier.background(CardAlt, RoundedCornerShape(12.dp)).padding(10.dp)) { Text(label, color = Muted, fontSize = 9.sp, fontWeight = FontWeight.Bold); Text(value, color = Text, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, maxLines = 1) }
}

@Composable
fun MetricCard(label: String, value: String, icon: ImageVector, modifier: Modifier = Modifier) {
    Card(modifier, colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(18.dp)) {
        Column(Modifier.padding(16.dp)) { Icon(icon, null, tint = Accent, modifier = Modifier.size(22.dp)); Spacer(Modifier.height(12.dp)); Text(label, color = Muted, fontSize = 11.sp); Text(value, color = Text, fontSize = 18.sp, fontWeight = FontWeight.Bold, maxLines = 1) }
    }
}

@Composable
fun PowerButton(text: String, icon: ImageVector, tint: Color, modifier: Modifier, action: () -> Unit) {
    Button(action, modifier.height(48.dp), colors = ButtonDefaults.buttonColors(containerColor = tint.copy(alpha = .16f), contentColor = tint), shape = RoundedCornerShape(14.dp), contentPadding = PaddingValues(horizontal = 8.dp)) { Icon(icon, null, Modifier.size(18.dp)); Spacer(Modifier.width(5.dp)); Text(text, fontSize = 12.sp, maxLines = 1) }
}

@Composable
fun StatusPill(state: String) {
    val c = when (state.lowercase()) { "running", "online" -> Success; "starting" -> Warning; "stopping" -> Warning; else -> Danger }
    Row(Modifier.background(c.copy(alpha = .13f), RoundedCornerShape(30.dp)).padding(horizontal = 9.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(6.dp).background(c, CircleShape)); Spacer(Modifier.width(6.dp)); Text(state.ifBlank { "unknown" }.replaceFirstChar { it.uppercase() }, color = c, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
fun ErrorBox(message: String, modifier: Modifier = Modifier) {
    Card(modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Danger.copy(alpha = .11f)), shape = RoundedCornerShape(14.dp)) { Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Default.ErrorOutline, null, tint = Danger); Spacer(Modifier.width(10.dp)); Text(message, color = Danger, fontSize = 12.sp) } }
}

@Composable
fun EmptyState(text: String) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { Column(horizontalAlignment = Alignment.CenterHorizontally) { Icon(Icons.Default.Inbox, null, tint = Muted, modifier = Modifier.size(42.dp)); Spacer(Modifier.height(8.dp)); Text(text, color = Muted) } }
}
