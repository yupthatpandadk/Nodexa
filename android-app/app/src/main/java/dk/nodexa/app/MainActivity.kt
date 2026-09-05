package dk.nodexa.app

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Cloud
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.Dns
import androidx.compose.material.icons.filled.Folder
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Stop
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
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

private val Bg = Color(0xFF091019)
private val Surface = Color(0xFF101925)
private val Surface2 = Color(0xFF172231)
private val Surface3 = Color(0xFF1D2A3A)
private val Accent = Color(0xFF6C7CFF)
private val Success = Color(0xFF4ADE80)
private val Danger = Color(0xFFFB7185)
private val Warning = Color(0xFFFBBF24)
private val Muted = Color(0xFF8D9AAF)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { MaterialTheme { NodexaApp(applicationContext) } }
    }
}

data class ServerItem(val id: String, val name: String, val description: String, val status: String)
data class SimpleItem(val title: String, val subtitle: String = "")
data class ServerMetrics(
    val state: String = "unknown",
    val cpu: Double = 0.0,
    val memoryBytes: Long = 0,
    val diskBytes: Long = 0,
    val uptimeMs: Long = 0,
)

enum class ServerTab(val label: String) {
    DASHBOARD("Overview"), CONSOLE("Console"), FILES("Files"), MORE("More")
}

enum class MoreSection(val label: String) {
    BACKUPS("Backups"), DATABASES("Databases"), PLUGINS("Plugins"), MODS("Mods")
}

class ApiClient(private val baseUrl: String, private val token: String) {
    private fun normalizedBase() = baseUrl.trim().trimEnd('/')

    private fun request(path: String, method: String = "GET", body: String? = null): String {
        val connection = (URL(normalizedBase() + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 10000
            readTimeout = 15000
            setRequestProperty("Authorization", "Bearer $token")
            setRequestProperty("Accept", "Application/vnd.pterodactyl.v1+json")
            setRequestProperty("Content-Type", "application/json")
            if (body != null) {
                doOutput = true
                outputStream.bufferedWriter().use { it.write(body) }
            }
        }
        val code = connection.responseCode
        val text = (if (code in 200..299) connection.inputStream else connection.errorStream)
            ?.bufferedReader()?.use { it.readText() }.orEmpty()
        if (code !in 200..299) throw IllegalStateException("HTTP $code: ${extractError(text)}")
        return text
    }

    private fun extractError(raw: String): String = try {
        val json = JSONObject(raw)
        val errors = json.optJSONArray("errors")
        errors?.optJSONObject(0)?.optString("detail")?.takeIf { it.isNotBlank() }
            ?: json.optString("message").takeIf { it.isNotBlank() }
            ?: "Ukendt API-fejl"
    } catch (_: Exception) {
        raw.take(180).ifBlank { "Ukendt API-fejl" }
    }

    fun servers(): List<ServerItem> {
        val json = JSONObject(request("/api/client"))
        val data = json.optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(ServerItem(
                    id = a.optString("identifier"),
                    name = a.optString("name", "Server"),
                    description = a.optString("description"),
                    status = a.optString("status", "unknown")
                ))
            }
        }
    }

    fun metrics(server: String): ServerMetrics {
        val root = JSONObject(request("/api/client/servers/$server/resources"))
        val a = root.optJSONObject("attributes")
            ?: root.optJSONObject("data")?.optJSONObject("attributes")
            ?: JSONObject()
        val resources = a.optJSONObject("resources") ?: JSONObject()
        return ServerMetrics(
            state = a.optString("current_state", "unknown"),
            cpu = resources.optDouble("cpu_absolute", 0.0),
            memoryBytes = resources.optLong("memory_bytes", 0L),
            diskBytes = resources.optLong("disk_bytes", 0L),
            uptimeMs = resources.optLong("uptime", 0L),
        )
    }

    fun power(server: String, signal: String) {
        request("/api/client/servers/$server/power", "POST", JSONObject().put("signal", signal).toString())
    }

    fun command(server: String, command: String) {
        request("/api/client/servers/$server/command", "POST", JSONObject().put("command", command).toString())
    }

    fun files(server: String): List<SimpleItem> {
        val json = JSONObject(request("/api/client/servers/$server/files/list?directory=%2F"))
        val data = json.optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                val name = a.optString("name", "Ukendt")
                val isFile = a.optBoolean("is_file", false)
                add(SimpleItem(name, if (isFile) "Fil · ${formatBytes(a.optLong("size", 0))}" else "Mappe"))
            }
        }
    }

    fun backups(server: String): List<SimpleItem> = listAttributes("/api/client/servers/$server/backups") { a ->
        SimpleItem(a.optString("name", "Backup"), if (a.optBoolean("is_successful", false)) "Klar" else "Behandler")
    }

    fun databases(server: String): List<SimpleItem> = listAttributes("/api/client/servers/$server/databases") { a ->
        SimpleItem(a.optString("name", "Database"), a.optString("host", "Database"))
    }

    fun plugins(server: String): List<SimpleItem> = installedAddon(server, "plugins")
    fun mods(server: String): List<SimpleItem> = installedAddon(server, "mods")

    private fun listAttributes(path: String, mapper: (JSONObject) -> SimpleItem): List<SimpleItem> {
        val data = JSONObject(request(path)).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(mapper(a))
            }
        }
    }

    private fun installedAddon(server: String, type: String): List<SimpleItem> {
        val data = JSONObject(request("/api/client/servers/$server/$type/installed")).optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i) ?: continue
                add(SimpleItem(
                    a.optString("name", a.optString("filename", type)),
                    listOf(a.optString("version_number"), a.optString("filename")).filter { it.isNotBlank() }.joinToString(" · ")
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
            val min = (sec % 3600) / 60
            return if (days > 0) "${days}d ${hours}h" else "${hours}h ${min}m"
        }
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
                    Text("Connect panel", color = Color.White, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Text("Brug din Nodexa Client API key.", color = Muted, style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(14.dp))
                    OutlinedTextField(value = baseUrl, onValueChange = setBase, modifier = Modifier.fillMaxWidth(), label = { Text("Panel URL") }, placeholder = { Text("https://panel.example.com") })
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(value = token, onValueChange = setToken, modifier = Modifier.fillMaxWidth(), label = { Text("nxa_ API key") }, placeholder = { Text("nxa_...") }, visualTransformation = PasswordVisualTransformation())
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = onSave, enabled = baseUrl.startsWith("https://") && token.isNotBlank(), modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Accent)) { Text("Connect") }
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
            runCatching { withContext(Dispatchers.IO) { api.servers() } }
                .onSuccess { servers = it }
                .onFailure { error = it.message ?: "Kunne ikke hente servere" }
            loading = false
        }
    }
    LaunchedEffect(Unit) { load() }

    Scaffold(containerColor = Bg, topBar = {
        TopAppBar(
            title = { Column { Text("Nodexa", color = Color.White, fontWeight = FontWeight.Bold); Text("Your servers", color = Muted, style = MaterialTheme.typography.labelSmall) } },
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
                    Text(error!!, color = Danger)
                    Spacer(Modifier.height(12.dp)); Button(onClick = { load() }) { Text("Prøv igen") }
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
        Column(Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.background(Accent.copy(alpha = .14f), RoundedCornerShape(14.dp)).padding(11.dp)) { Icon(Icons.Default.Dns, null, tint = Accent) }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(server.name, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
                    Text(server.description.ifBlank { "Game server" }, color = Muted, style = MaterialTheme.typography.bodySmall)
                }
                StatusPill(server.status)
            }
            Spacer(Modifier.height(14.dp))
            Text("Tap to manage server", color = Muted, style = MaterialTheme.typography.labelSmall)
        }
    }
}

@Composable
fun StatusPill(status: String) {
    val online = status.equals("running", true)
    val color = if (online) Success else Muted
    Box(Modifier.background(color.copy(alpha = .14f), RoundedCornerShape(20.dp)).padding(horizontal = 10.dp, vertical = 6.dp)) {
        Text(if (online) "ONLINE" else status.uppercase().ifBlank { "UNKNOWN" }, color = color, style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerScreen(api: ApiClient, server: ServerItem, onBack: () -> Unit) {
    var tab by remember { mutableStateOf(ServerTab.DASHBOARD) }
    Scaffold(
        containerColor = Bg,
        topBar = {
            TopAppBar(
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, null, tint = Color.White) } },
                title = { Column { Text(server.name, color = Color.White, fontWeight = FontWeight.Bold); Text(server.id, color = Muted, style = MaterialTheme.typography.labelSmall) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Bg)
            )
        },
        bottomBar = {
            NavigationBar(containerColor = Surface) {
                ServerTab.entries.forEach { item ->
                    NavigationBarItem(
                        selected = tab == item,
                        onClick = { tab = item },
                        icon = {
                            Icon(
                                when (item) {
                                    ServerTab.DASHBOARD -> Icons.Default.Dashboard
                                    ServerTab.CONSOLE -> Icons.Default.Terminal
                                    ServerTab.FILES -> Icons.Default.Folder
                                    ServerTab.MORE -> Icons.Default.MoreHoriz
                                }, null
                            )
                        },
                        label = { Text(item.label) }
                    )
                }
            }
        }
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).padding(16.dp)) {
            when (tab) {
                ServerTab.DASHBOARD -> DashboardPanel(api, server)
                ServerTab.CONSOLE -> ConsolePanel(api, server)
                ServerTab.FILES -> ResourcePanel(api, server, MoreSection.PLUGINS, filesMode = true)
                ServerTab.MORE -> MorePanel(api, server)
            }
        }
    }
}

@Composable
fun DashboardPanel(api: ApiClient, server: ServerItem) {
    val scope = rememberCoroutineScope()
    var metrics by remember { mutableStateOf(ServerMetrics(state = server.status)) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    fun refresh() {
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.metrics(server.id) } }
                .onSuccess { metrics = it; error = null }
                .onFailure { error = it.message }
        }
    }
    fun signal(value: String) {
        busy = true
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.power(server.id, value) } }
                .onFailure { error = it.message }
            delay(900); refresh(); busy = false
        }
    }

    LaunchedEffect(server.id) {
        while (true) { refresh(); delay(5000) }
    }

    LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("Server status", color = Muted, style = MaterialTheme.typography.labelMedium)
                            Spacer(Modifier.height(4.dp))
                            Text(metrics.state.replaceFirstChar { it.uppercase() }, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.headlineSmall)
                        }
                        StatusPill(metrics.state)
                    }
                    Spacer(Modifier.height(18.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(onClick = { signal("start") }, enabled = !busy, modifier = Modifier.weight(1f), colors = ButtonDefaults.buttonColors(containerColor = Success)) { Icon(Icons.Default.PlayArrow, null); Spacer(Modifier.width(4.dp)); Text("Start") }
                        Button(onClick = { signal("restart") }, enabled = !busy, modifier = Modifier.weight(1f), colors = ButtonDefaults.buttonColors(containerColor = Accent)) { Icon(Icons.Default.Refresh, null); Spacer(Modifier.width(4.dp)); Text("Restart") }
                        OutlinedButton(onClick = { signal("stop") }, enabled = !busy, modifier = Modifier.weight(1f)) { Icon(Icons.Default.Stop, null); Spacer(Modifier.width(4.dp)); Text("Stop") }
                    }
                }
            }
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                MetricCard("CPU", String.format("%.1f%%", metrics.cpu), (metrics.cpu / 100.0).toFloat().coerceIn(0f, 1f), Modifier.weight(1f))
                MetricCard("Memory", ApiClient.formatBytes(metrics.memoryBytes), 0f, Modifier.weight(1f))
            }
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                MetricCard("Disk", ApiClient.formatBytes(metrics.diskBytes), 0f, Modifier.weight(1f))
                MetricCard("Uptime", ApiClient.formatUptime(metrics.uptimeMs), 0f, Modifier.weight(1f))
            }
        }
        if (error != null) item { Text(error!!, color = Danger, style = MaterialTheme.typography.bodySmall) }
    }
}

@Composable
fun MetricCard(title: String, value: String, progress: Float, modifier: Modifier = Modifier) {
    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(18.dp), modifier = modifier) {
        Column(Modifier.padding(15.dp)) {
            Text(title, color = Muted, style = MaterialTheme.typography.labelMedium)
            Spacer(Modifier.height(6.dp))
            Text(value, color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
            if (progress > 0f) {
                Spacer(Modifier.height(10.dp)); LinearProgressIndicator(progress = { progress }, modifier = Modifier.fillMaxWidth(), color = if (progress > .85f) Warning else Accent)
            }
        }
    }
}

@Composable
fun ConsolePanel(api: ApiClient, server: ServerItem) {
    val scope = rememberCoroutineScope()
    var command by remember { mutableStateOf("") }
    var message by remember { mutableStateOf("Ready to send commands") }
    var busy by remember { mutableStateOf(false) }

    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Terminal, null, tint = Accent)
                Spacer(Modifier.width(10.dp))
                Column { Text("Console", color = Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleLarge); Text("Send commands directly to the server", color = Muted, style = MaterialTheme.typography.bodySmall) }
            }
            Spacer(Modifier.height(18.dp))
            Box(Modifier.fillMaxWidth().height(230.dp).background(Color(0xFF080D13), RoundedCornerShape(14.dp)).padding(14.dp)) {
                Text(message, color = Color(0xFFB9C7D8), style = MaterialTheme.typography.bodySmall)
            }
            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                OutlinedTextField(value = command, onValueChange = { command = it }, modifier = Modifier.weight(1f), placeholder = { Text("Enter command…") }, singleLine = true)
                Spacer(Modifier.width(8.dp))
                IconButton(enabled = command.isNotBlank() && !busy, onClick = {
                    val value = command.trim(); command = ""; busy = true
                    scope.launch {
                        runCatching { withContext(Dispatchers.IO) { api.command(server.id, value) } }
                            .onSuccess { message = "> $value\nCommand sent successfully." }
                            .onFailure { message = it.message ?: "Command failed" }
                        busy = false
                    }
                }) { Icon(Icons.Default.Send, null, tint = Accent) }
            }
        }
    }
}

@Composable
fun MorePanel(api: ApiClient, server: ServerItem) {
    var section by remember { mutableStateOf(MoreSection.BACKUPS) }
    Column {
        LazyColumn(Modifier.fillMaxWidth().height(52.dp), horizontalAlignment = Alignment.Start) {
            item {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    MoreSection.entries.forEach { s ->
                        TextButton(onClick = { section = s }, colors = ButtonDefaults.textButtonColors(contentColor = if (section == s) Accent else Muted)) { Text(s.label) }
                    }
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        ResourcePanel(api, server, section)
    }
}

@Composable
fun ResourcePanel(api: ApiClient, server: ServerItem, section: MoreSection, filesMode: Boolean = false) {
    val scope = rememberCoroutineScope()
    var data by remember(section, server.id, filesMode) { mutableStateOf<List<SimpleItem>>(emptyList()) }
    var loading by remember(section, server.id, filesMode) { mutableStateOf(true) }
    var error by remember(section, server.id, filesMode) { mutableStateOf<String?>(null) }

    fun load() {
        loading = true; error = null
        scope.launch {
            runCatching {
                withContext(Dispatchers.IO) {
                    if (filesMode) api.files(server.id) else when (section) {
                        MoreSection.BACKUPS -> api.backups(server.id)
                        MoreSection.DATABASES -> api.databases(server.id)
                        MoreSection.PLUGINS -> api.plugins(server.id)
                        MoreSection.MODS -> api.mods(server.id)
                    }
                }
            }.onSuccess { data = it }.onFailure { error = it.message }
            loading = false
        }
    }
    LaunchedEffect(section, server.id, filesMode) { load() }

    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(if (filesMode) "Files" else section.label, color = Color.White, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Accent) }
            }
            when {
                loading -> Box(Modifier.fillMaxWidth().height(120.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Accent) }
                error != null -> Text(error!!, color = Danger, modifier = Modifier.padding(vertical = 14.dp))
                data.isEmpty() -> Text("Ingen elementer fundet.", color = Muted, modifier = Modifier.padding(vertical = 14.dp))
                else -> data.forEach { item ->
                    Row(Modifier.fillMaxWidth().background(Surface2, RoundedCornerShape(14.dp)).padding(13.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(if (filesMode) Icons.Default.Folder else Icons.Default.Cloud, null, tint = Accent)
                        Spacer(Modifier.width(10.dp))
                        Column(Modifier.weight(1f)) {
                            Text(item.title, color = Color.White, fontWeight = FontWeight.Medium)
                            if (item.subtitle.isNotBlank()) Text(item.subtitle, color = Muted, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
            }
        }
    }
}
