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
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Folder
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
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
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

private val Bg = Color(0xFF07111D)
private val Surface = Color(0xFF0E1C2C)
private val Surface2 = Color(0xFF13263B)
private val Accent = Color(0xFF2F80ED)
private val Success = Color(0xFF34D399)
private val Danger = Color(0xFFFB7185)
private val Muted = Color(0xFF91A4B7)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                NodexaApp(applicationContext)
            }
        }
    }
}

data class ServerItem(val id: String, val name: String, val description: String, val status: String)
data class SimpleItem(val title: String, val subtitle: String = "")

enum class ServerTab(val label: String) {
    OVERVIEW("Overview"), FILES("Files"), BACKUPS("Backups"), DATABASES("Databases"), PLUGINS("Plugins"), MODS("Mods")
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
                add(
                    ServerItem(
                        id = a.optString("identifier"),
                        name = a.optString("name", "Server"),
                        description = a.optString("description"),
                        status = a.optString("status", "unknown")
                    )
                )
            }
        }
    }

    fun power(server: String, signal: String) {
        request("/api/client/servers/$server/power", "POST", JSONObject().put("signal", signal).toString())
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

    fun backups(server: String): List<SimpleItem> {
        val json = JSONObject(request("/api/client/servers/$server/backups"))
        val data = json.optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(SimpleItem(a.optString("name", "Backup"), if (a.optBoolean("is_successful", false)) "Klar" else "Behandler"))
            }
        }
    }

    fun databases(server: String): List<SimpleItem> {
        val json = JSONObject(request("/api/client/servers/$server/databases"))
        val data = json.optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i)?.optJSONObject("attributes") ?: continue
                add(SimpleItem(a.optString("name", "Database"), a.optString("host", "Database")))
            }
        }
    }

    fun plugins(server: String): List<SimpleItem> = installedAddon(server, "plugins")
    fun mods(server: String): List<SimpleItem> = installedAddon(server, "mods")

    private fun installedAddon(server: String, type: String): List<SimpleItem> {
        val json = JSONObject(request("/api/client/servers/$server/$type/installed"))
        val data = json.optJSONArray("data") ?: JSONArray()
        return buildList {
            for (i in 0 until data.length()) {
                val a = data.optJSONObject(i) ?: continue
                add(
                    SimpleItem(
                        title = a.optString("name", a.optString("filename", type)),
                        subtitle = listOf(a.optString("version_number"), a.optString("filename")).filter { it.isNotBlank() }.joinToString(" · ")
                    )
                )
            }
        }
    }

    companion object {
        fun formatBytes(bytes: Long): String = when {
            bytes >= 1024 * 1024 -> String.format("%.1f MB", bytes / 1024.0 / 1024.0)
            bytes >= 1024 -> String.format("%.0f KB", bytes / 1024.0)
            else -> "$bytes B"
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
        selected == null -> ServerListScreen(ApiClient(baseUrl, token), onOpen = { selected = it }, onLogout = {
            prefs.edit().clear().apply()
            configured = false
            token = ""
        })
        else -> ServerScreen(ApiClient(baseUrl, token), selected!!, onBack = { selected = null })
    }
}

@Composable
fun LoginScreen(baseUrl: String, token: String, setBase: (String) -> Unit, setToken: (String) -> Unit, onSave: () -> Unit) {
    Box(Modifier.fillMaxSize().background(Bg).padding(24.dp), contentAlignment = Alignment.Center) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
            Box(Modifier.background(Accent, RoundedCornerShape(18.dp)).padding(horizontal = 18.dp, vertical = 12.dp)) {
                Text("N", color = Color.White, fontWeight = FontWeight.Black, style = MaterialTheme.typography.headlineMedium)
            }
            Spacer(Modifier.height(18.dp))
            Text("Nodexa", color = Color.White, style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
            Text("GAME SERVER CLOUD", color = Accent, style = MaterialTheme.typography.labelSmall)
            Spacer(Modifier.height(30.dp))
            Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(20.dp)) {
                Column(Modifier.padding(18.dp)) {
                    Text("Forbind til dit panel", color = Color.White, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(value = baseUrl, onValueChange = setBase, modifier = Modifier.fillMaxWidth(), label = { Text("Panel URL") }, placeholder = { Text("https://panel.example.com") })
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(value = token, onValueChange = setToken, modifier = Modifier.fillMaxWidth(), label = { Text("Client API key") }, visualTransformation = PasswordVisualTransformation())
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = onSave, enabled = baseUrl.startsWith("https://") && token.isNotBlank(), modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Accent)) {
                        Text("Fortsæt")
                    }
                    Spacer(Modifier.height(8.dp))
                    Text("API-nøglen gemmes kun lokalt på enheden.", color = Muted, style = MaterialTheme.typography.bodySmall)
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
        loading = true
        error = null
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.servers() } }
                .onSuccess { servers = it }
                .onFailure { error = it.message ?: "Kunne ikke hente servere" }
            loading = false
        }
    }
    LaunchedEffect(Unit) { load() }

    Scaffold(containerColor = Bg, topBar = {
        TopAppBar(title = { Text("Nodexa", fontWeight = FontWeight.Bold) }, colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface, titleContentColor = Color.White), actions = {
            IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Color.White) }
            IconButton(onClick = onLogout) { Icon(Icons.Default.Settings, null, tint = Color.White) }
        })
    }) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).padding(16.dp)) {
            when {
                loading -> CircularProgressIndicator(Modifier.align(Alignment.Center), color = Accent)
                error != null -> Column(Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(error!!, color = Danger)
                    Spacer(Modifier.height(12.dp))
                    Button(onClick = { load() }) { Text("Prøv igen") }
                }
                servers.isEmpty() -> Text("Ingen servere fundet.", color = Muted, modifier = Modifier.align(Alignment.Center))
                else -> LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    item {
                        Text("Dine servere", color = Color.White, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                        Text("Administrér dine Nodexa-servere fra mobilen.", color = Muted)
                        Spacer(Modifier.height(6.dp))
                    }
                    items(servers) { server ->
                        Card(onClick = { onOpen(server) }, colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(18.dp), modifier = Modifier.fillMaxWidth()) {
                            Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                                Box(Modifier.background(Accent.copy(alpha = .16f), RoundedCornerShape(14.dp)).padding(12.dp)) { Icon(Icons.Default.Cloud, null, tint = Accent) }
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(server.name, color = Color.White, fontWeight = FontWeight.SemiBold)
                                    Text(server.description.ifBlank { "Game server" }, color = Muted, style = MaterialTheme.typography.bodySmall)
                                }
                                val statusColor = if (server.status == "running") Success else Muted
                                Text(server.status.ifBlank { "unknown" }, color = statusColor, style = MaterialTheme.typography.labelMedium)
                            }
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ServerScreen(api: ApiClient, server: ServerItem, onBack: () -> Unit) {
    var tab by remember { mutableStateOf(ServerTab.OVERVIEW) }
    Scaffold(containerColor = Bg, topBar = {
        TopAppBar(navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, null, tint = Color.White) } }, title = {
            Column { Text(server.name, color = Color.White, fontWeight = FontWeight.Bold); Text(server.id, color = Muted, style = MaterialTheme.typography.labelSmall) }
        }, colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface))
    }) { pad ->
        Column(Modifier.fillMaxSize().padding(pad)) {
            LazyColumn(Modifier.fillMaxWidth().weight(1f).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                item {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        ServerTab.entries.forEach { value ->
                            TextButton(onClick = { tab = value }, colors = ButtonDefaults.textButtonColors(contentColor = if (tab == value) Accent else Muted)) { Text(value.label, style = MaterialTheme.typography.labelSmall) }
                        }
                    }
                }
                item {
                    when (tab) {
                        ServerTab.OVERVIEW -> OverviewPanel(api, server)
                        else -> ResourcePanel(api, server, tab)
                    }
                }
            }
        }
    }
}

@Composable
fun OverviewPanel(api: ApiClient, server: ServerItem) {
    val scope = rememberCoroutineScope()
    var message by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    fun signal(value: String) {
        busy = true
        message = null
        scope.launch {
            runCatching { withContext(Dispatchers.IO) { api.power(server.id, value) } }
                .onSuccess { message = "Kommando sendt: $value" }
                .onFailure { message = it.message }
            busy = false
        }
    }

    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(18.dp), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(18.dp)) {
            Text("Server control", color = Color.White, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text("Start, genstart eller stop serveren direkte fra appen.", color = Muted)
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(onClick = { signal("start") }, enabled = !busy, colors = ButtonDefaults.buttonColors(containerColor = Success)) { Icon(Icons.Default.PlayArrow, null); Spacer(Modifier.width(4.dp)); Text("Start") }
                Button(onClick = { signal("restart") }, enabled = !busy, colors = ButtonDefaults.buttonColors(containerColor = Accent)) { Icon(Icons.Default.Refresh, null); Spacer(Modifier.width(4.dp)); Text("Restart") }
                OutlinedButton(onClick = { signal("stop") }, enabled = !busy) { Icon(Icons.Default.Stop, null); Spacer(Modifier.width(4.dp)); Text("Stop") }
            }
            if (message != null) { Spacer(Modifier.height(12.dp)); Text(message!!, color = if (message!!.startsWith("HTTP")) Danger else Muted, style = MaterialTheme.typography.bodySmall) }
        }
    }
}

@Composable
fun ResourcePanel(api: ApiClient, server: ServerItem, tab: ServerTab) {
    val scope = rememberCoroutineScope()
    var data by remember(tab, server.id) { mutableStateOf<List<SimpleItem>>(emptyList()) }
    var loading by remember(tab, server.id) { mutableStateOf(true) }
    var error by remember(tab, server.id) { mutableStateOf<String?>(null) }

    fun load() {
        loading = true
        error = null
        scope.launch {
            runCatching {
                withContext(Dispatchers.IO) {
                    when (tab) {
                        ServerTab.FILES -> api.files(server.id)
                        ServerTab.BACKUPS -> api.backups(server.id)
                        ServerTab.DATABASES -> api.databases(server.id)
                        ServerTab.PLUGINS -> api.plugins(server.id)
                        ServerTab.MODS -> api.mods(server.id)
                        else -> emptyList()
                    }
                }
            }.onSuccess { data = it }.onFailure { error = it.message }
            loading = false
        }
    }
    LaunchedEffect(tab, server.id) { load() }

    Card(colors = CardDefaults.cardColors(containerColor = Surface), shape = RoundedCornerShape(18.dp), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(tab.label, color = Color.White, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                IconButton(onClick = { load() }) { Icon(Icons.Default.Refresh, null, tint = Accent) }
            }
            when {
                loading -> Box(Modifier.fillMaxWidth().height(120.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Accent) }
                error != null -> Text(error!!, color = Danger, modifier = Modifier.padding(vertical = 14.dp))
                data.isEmpty() -> Text("Ingen elementer fundet.", color = Muted, modifier = Modifier.padding(vertical = 14.dp))
                else -> data.forEach { item ->
                    Row(Modifier.fillMaxWidth().background(Surface2, RoundedCornerShape(12.dp)).padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(if (tab == ServerTab.FILES) Icons.Default.Folder else Icons.Default.Cloud, null, tint = Accent)
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
