package org.nodexa.app

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

private val Bg = Color(0xFF07131F)
private val Top = Color(0xFF171B29)
private val CardBg = Color(0xFF102132)
private val Blue = Color(0xFF3585EA)
private val Muted = Color(0xFF9BA9BC)
private val White = Color(0xFFF8FAFD)
private val Red = Color(0xFFFF718B)
private val Green = Color(0xFF37D29F)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    primary = Blue,
                    background = Bg,
                    surface = CardBg,
                    onSurface = White,
                    onBackground = White
                )
            ) {
                NodexaApp(this)
            }
        }
    }
}

data class ApiResponse(val code: Int, val body: String) {
    val ok: Boolean get() = code in 200..299
}

class ApiClient(private val base: String, private val token: String) {
    suspend fun request(method: String, path: String, json: JSONObject? = null): ApiResponse = withContext(Dispatchers.IO) {
        val connection = URL(base.trimEnd('/') + "/api" + path).openConnection() as HttpURLConnection
        connection.requestMethod = method
        connection.connectTimeout = 12000
        connection.readTimeout = 30000
        connection.setRequestProperty("Authorization", "Bearer $token")
        connection.setRequestProperty("Accept", "application/json,text/plain,*/*")
        if (json != null) {
            connection.doOutput = true
            connection.setRequestProperty("Content-Type", "application/json")
            connection.outputStream.use { it.write(json.toString().toByteArray()) }
        }
        val code = connection.responseCode
        val stream = if (code in 200..299) connection.inputStream else connection.errorStream
        ApiResponse(code, stream?.bufferedReader()?.use { it.readText() }.orEmpty())
    }

    suspend fun get(path: String) = request("GET", path)
    suspend fun post(path: String, json: JSONObject = JSONObject()) = request("POST", path, json)
    suspend fun put(path: String, json: JSONObject) = request("PUT", path, json)
    suspend fun delete(path: String, json: JSONObject? = null) = request("DELETE", path, json)
}

@Composable
fun NodexaApp(context: Context) {
    val preferences = remember { context.getSharedPreferences("nodexa", 0) }
    var base by remember { mutableStateOf(preferences.getString("base", "") ?: "") }
    var token by remember { mutableStateOf(preferences.getString("token", "") ?: "") }
    var selectedServer by remember { mutableStateOf<JSONObject?>(null) }

    Surface(Modifier.fillMaxSize(), color = Bg) {
        when {
            base.isBlank() || token.isBlank() -> ConnectScreen(base, token) { newBase, newToken ->
                base = newBase.trimEnd('/')
                token = newToken
                preferences.edit().putString("base", base).putString("token", token).apply()
            }
            selectedServer == null -> ServerListScreen(
                api = ApiClient(base, token),
                onOpen = { selectedServer = it },
                onLogout = {
                    preferences.edit().clear().apply()
                    base = ""
                    token = ""
                }
            )
            else -> ServerScreen(context, ApiClient(base, token), selectedServer!!) { selectedServer = null }
        }
    }
}

@Composable
private fun NodexaField(value: String, onValueChange: (String) -> Unit, label: String, placeholder: String = "", password: Boolean = false) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        placeholder = { Text(placeholder, color = Muted) },
        singleLine = true,
        textStyle = LocalTextStyle.current.copy(color = White),
        visualTransformation = if (password) PasswordVisualTransformation() else VisualTransformation.None,
        colors = OutlinedTextFieldDefaults.colors(
            focusedTextColor = White,
            unfocusedTextColor = White,
            focusedBorderColor = Blue,
            unfocusedBorderColor = Muted,
            cursorColor = Blue
        ),
        modifier = Modifier.fillMaxWidth()
    )
}

@Composable
private fun ConnectScreen(initialBase: String, initialToken: String, connect: (String, String) -> Unit) {
    var base by remember { mutableStateOf(initialBase) }
    var token by remember { mutableStateOf(initialToken) }
    Column(
        Modifier.fillMaxSize().padding(28.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.Start
    ) {
        Box(Modifier.size(82.dp).background(Blue, RoundedCornerShape(22.dp)), contentAlignment = Alignment.Center) {
            Text("N", fontSize = 42.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(18.dp))
        Text("Nodexa", fontSize = 40.sp, fontWeight = FontWeight.Bold)
        Text("GAME SERVER CLOUD", color = Blue)
        Spacer(Modifier.height(32.dp))
        Text("Forbind til dit panel", fontSize = 24.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(14.dp))
        NodexaField(base, { base = it }, "Panel URL", "https://panel.example.com")
        Spacer(Modifier.height(10.dp))
        NodexaField(token, { token = it }, "Client API key", "nxa_...", true)
        Spacer(Modifier.height(16.dp))
        Button(
            onClick = { if (base.isNotBlank() && token.isNotBlank()) connect(base, token) },
            modifier = Modifier.fillMaxWidth()
        ) { Text("Forbind") }
        Text("API-nøglen gemmes kun lokalt på enheden.", color = Muted, modifier = Modifier.padding(top = 10.dp))
    }
}

@Composable
private fun AppBar(title: String, back: (() -> Unit)? = null, actions: @Composable RowScope.() -> Unit = {}) {
    Row(
        Modifier.fillMaxWidth().background(Top).statusBarsPadding().height(70.dp).padding(horizontal = 8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        if (back != null) IconButton(back) { Icon(Icons.Default.ArrowBack, null) }
        Text(title, fontSize = 25.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
        actions()
    }
}

private fun objectList(text: String): List<JSONObject> {
    return runCatching {
        val trimmed = text.trim()
        val array = if (trimmed.startsWith("[")) JSONArray(trimmed) else JSONObject(trimmed).optJSONArray("data") ?: JSONArray()
        (0 until array.length()).mapNotNull { array.optJSONObject(it) }
    }.getOrDefault(emptyList())
}

private fun errorMessage(body: String): String = runCatching {
    JSONObject(body).optString("message", body.take(180))
}.getOrDefault(body.take(180))

private fun encode(value: String): String = URLEncoder.encode(value, "UTF-8")
private fun childPath(current: String, name: String): String = if (current == "/") "/$name" else current.trimEnd('/') + "/$name"

@Composable
private fun ServerListScreen(api: ApiClient, onOpen: (JSONObject) -> Unit, onLogout: () -> Unit) {
    var servers by remember { mutableStateOf<List<JSONObject>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    fun load() {
        scope.launch {
            val response = api.get("/servers")
            if (response.ok) {
                servers = objectList(response.body)
                error = ""
            } else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
        }
    }
    LaunchedEffect(Unit) { load() }

    Column {
        AppBar("Nodexa", actions = {
            IconButton({ load() }) { Icon(Icons.Default.Refresh, null) }
            IconButton(onLogout) { Icon(Icons.Default.Logout, null) }
        })
        Text("Dine servere", fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(18.dp))
        if (error.isNotBlank()) Text(error, color = Red, modifier = Modifier.padding(horizontal = 18.dp))
        LazyColumn(contentPadding = PaddingValues(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            items(servers) { server ->
                Card(
                    Modifier.fillMaxWidth().clickable { onOpen(server) },
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF172E46))
                ) {
                    Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Dns, null, tint = Blue)
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(server.optString("name", "Server"), fontSize = 20.sp, fontWeight = FontWeight.Bold)
                            Text(server.optString("identifier", ""), color = Muted)
                        }
                        Icon(Icons.Default.ChevronRight, null)
                    }
                }
            }
        }
    }
}

@Composable
private fun ServerScreen(context: Context, api: ApiClient, server: JSONObject, back: () -> Unit) {
    val id = server.optInt("id")
    var tab by remember { mutableIntStateOf(0) }
    val tabs = listOf("Overview", "Console", "Files", "Backups", "Databases", "Schedules", "Users", "Settings")
    Column {
        AppBar(server.optString("name", "Nodexa"), back)
        ScrollableTabRow(selectedTabIndex = tab, containerColor = Bg, edgePadding = 4.dp) {
            tabs.forEachIndexed { index, name -> Tab(selected = tab == index, onClick = { tab = index }, text = { Text(name) }) }
        }
        when (tab) {
            0 -> OverviewTab(api, id)
            1 -> ConsoleTab(api, id)
            2 -> FilesTab(api, id)
            3 -> BackupsTab(api, id)
            4 -> DatabasesTab(context, api, id)
            5 -> SchedulesTab(api, id)
            6 -> UsersTab(api, id)
            else -> SettingsTab(api, id, server)
        }
    }
}

@Composable
private fun SectionCard(title: String, content: @Composable ColumnScope.() -> Unit) {
    Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = CardBg)) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(title, fontSize = 21.sp, fontWeight = FontWeight.Bold)
            content()
        }
    }
}

@Composable
private fun OverviewTab(api: ApiClient, id: Int) {
    var stats by remember { mutableStateOf(JSONObject()) }
    var info by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    suspend fun load() {
        val response = api.get("/servers/$id/stats")
        if (response.ok) stats = runCatching { JSONObject(response.body) }.getOrDefault(JSONObject())
    }
    LaunchedEffect(Unit) { while (true) { load(); delay(4000) } }
    fun power(action: String) {
        scope.launch {
            val response = api.post("/servers/$id/power", JSONObject().put("action", action))
            info = if (response.ok) "Kommando sendt" else "HTTP ${response.code}: ${errorMessage(response.body)}"
            load()
        }
    }
    Column(Modifier.verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Server control", fontSize = 28.sp, fontWeight = FontWeight.Bold)
        Text("Status: ${stats.optString("status", stats.optString("state", "—"))}", color = Muted)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button({ power("start") }, colors = ButtonDefaults.buttonColors(containerColor = Green)) { Text("Start") }
            Button({ power("restart") }) { Text("Restart") }
            OutlinedButton({ power("stop") }) { Text("Stop") }
        }
        if (info.isNotBlank()) Text(info, color = Muted)
        SectionCard("Resources") {
            listOf("cpu", "memory", "disk", "network_rx", "network_tx").forEach { key ->
                Row {
                    Text(key.replace('_', ' ').uppercase(), color = Muted, modifier = Modifier.weight(1f))
                    Text(stats.optString(key, "—"))
                }
            }
        }
    }
}

@Composable
private fun ConsoleTab(api: ApiClient, id: Int) {
    var log by remember { mutableStateOf("Henter...") }
    var command by remember { mutableStateOf("") }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    LaunchedEffect(Unit) {
        while (true) {
            val response = api.get("/servers/$id/logs?tail=300")
            if (response.ok) log = response.body else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
            delay(2500)
        }
    }
    Column(Modifier.fillMaxSize().padding(12.dp)) {
        Text("Live console", fontSize = 26.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(8.dp))
        Card(Modifier.weight(1f).fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Color(0xFF050B12))) {
            Text(log, fontSize = 12.sp, color = Color(0xFFD6E1F0), modifier = Modifier.fillMaxSize().padding(10.dp).verticalScroll(rememberScrollState()))
        }
        if (error.isNotBlank()) Text(error, color = Red)
        Row(verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(command, { command = it }, modifier = Modifier.weight(1f), placeholder = { Text("Kommando", color = Muted) }, textStyle = LocalTextStyle.current.copy(color = White))
            IconButton({
                val value = command
                if (value.isNotBlank()) scope.launch {
                    val response = api.post("/servers/$id/command", JSONObject().put("command", value))
                    if (response.ok) command = "" else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
                }
            }) { Icon(Icons.Default.Send, null, tint = Blue) }
        }
    }
}

data class FileEntry(val name: String, val directory: Boolean, val size: Long)

private fun fileList(text: String): List<FileEntry> {
    return runCatching {
        val root = if (text.trim().startsWith("[")) JSONArray(text) else {
            val obj = JSONObject(text)
            obj.optJSONArray("files") ?: obj.optJSONArray("data") ?: JSONArray()
        }
        (0 until root.length()).mapNotNull { i ->
            root.optJSONObject(i)?.let { obj ->
                FileEntry(
                    obj.optString("name", obj.optString("basename", "?")),
                    obj.optBoolean("is_dir", obj.optString("type") == "directory"),
                    obj.optLong("size")
                )
            }
        }
    }.getOrDefault(emptyList())
}

@Composable
private fun FilesTab(api: ApiClient, id: Int) {
    var currentPath by remember { mutableStateOf("/") }
    var files by remember { mutableStateOf<List<FileEntry>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    var editing by remember { mutableStateOf<String?>(null) }
    var content by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()

    suspend fun load() {
        val response = api.get("/servers/$id/files?path=${encode(currentPath)}")
        if (response.ok) { files = fileList(response.body); error = "" }
        else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
    }
    LaunchedEffect(currentPath) { load() }

    if (editing != null) {
        Column(Modifier.fillMaxSize().padding(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton({ editing = null }) { Icon(Icons.Default.ArrowBack, null) }
                Text(editing!!, modifier = Modifier.weight(1f), maxLines = 1)
                IconButton({
                    scope.launch {
                        val response = api.put("/servers/$id/file", JSONObject().put("path", editing).put("content", content))
                        error = if (response.ok) "Gemt" else "HTTP ${response.code}: ${errorMessage(response.body)}"
                    }
                }) { Icon(Icons.Default.Save, null, tint = Green) }
            }
            OutlinedTextField(content, { content = it }, modifier = Modifier.fillMaxSize(), textStyle = LocalTextStyle.current.copy(color = White))
        }
        return
    }

    Column {
        Row(Modifier.padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton({ if (currentPath != "/") currentPath = currentPath.trimEnd('/').substringBeforeLast('/').ifBlank { "/" } }) { Icon(Icons.Default.ArrowUpward, null) }
            Text(currentPath, modifier = Modifier.weight(1f), color = Muted, maxLines = 1)
            IconButton({ scope.launch { load() } }) { Icon(Icons.Default.Refresh, null) }
        }
        if (error.isNotBlank()) Text(error, color = if (error == "Gemt") Green else Red, modifier = Modifier.padding(horizontal = 8.dp))
        LazyColumn(contentPadding = PaddingValues(10.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(files) { file ->
                Card(Modifier.fillMaxWidth().clickable {
                    scope.launch {
                        if (file.directory) currentPath = childPath(currentPath, file.name)
                        else {
                            val fullPath = childPath(currentPath, file.name)
                            val response = api.get("/servers/$id/file?path=${encode(fullPath)}")
                            if (response.ok) { editing = fullPath; content = response.body }
                            else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
                        }
                    }
                }, colors = CardDefaults.cardColors(containerColor = Color(0xFF172E46))) {
                    Row(Modifier.padding(15.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(if (file.directory) Icons.Default.Folder else Icons.Default.InsertDriveFile, null, tint = Blue)
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(file.name, fontWeight = FontWeight.Bold)
                            if (!file.directory) Text("${file.size} bytes", color = Muted, fontSize = 12.sp)
                        }
                        IconButton({
                            scope.launch {
                                val response = api.delete("/servers/$id/file", JSONObject().put("path", childPath(currentPath, file.name)))
                                if (response.ok) load() else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
                            }
                        }) { Icon(Icons.Default.Delete, null, tint = Red) }
                    }
                }
            }
        }
    }
}

private fun backupNames(text: String): List<String> {
    return runCatching {
        val array = if (text.trim().startsWith("[")) JSONArray(text) else {
            val obj = JSONObject(text)
            obj.optJSONArray("backups") ?: obj.optJSONArray("data") ?: JSONArray()
        }
        (0 until array.length()).mapNotNull { i ->
            when (val value = array.get(i)) {
                is String -> value
                is JSONObject -> value.optString("name").takeIf { it.isNotBlank() }
                else -> null
            }
        }
    }.getOrDefault(emptyList())
}

@Composable
private fun BackupsTab(api: ApiClient, id: Int) {
    var backups by remember { mutableStateOf<List<String>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    suspend fun load() {
        val response = api.get("/servers/$id/backups")
        if (response.ok) backups = backupNames(response.body) else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
    }
    LaunchedEffect(Unit) { load() }
    Column(Modifier.padding(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Backups", fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            IconButton({ scope.launch { val r = api.post("/servers/$id/backups", JSONObject().put("name", "mobile-backup")); if (r.ok) load() else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Icon(Icons.Default.Add, null, tint = Green) }
            IconButton({ scope.launch { load() } }) { Icon(Icons.Default.Refresh, null, tint = Blue) }
        }
        if (error.isNotBlank()) Text(error, color = Red)
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(backups) { name ->
                SectionCard(name) {
                    Row {
                        TextButton({ scope.launch { val r = api.post("/servers/$id/backups/${encode(name)}/restore"); error = if (r.ok) "Restore startet" else "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Restore") }
                        TextButton({ scope.launch { val r = api.delete("/servers/$id/backups/${encode(name)}"); if (r.ok) load() else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Slet", color = Red) }
                    }
                }
            }
        }
    }
}

@Composable
private fun DatabasesTab(context: Context, api: ApiClient, id: Int) {
    var databases by remember { mutableStateOf<List<JSONObject>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    var newName by remember { mutableStateOf("") }
    var credentials by remember { mutableStateOf<JSONObject?>(null) }
    val scope = rememberCoroutineScope()
    suspend fun load() {
        val response = api.get("/servers/$id/databases")
        if (response.ok) { databases = objectList(response.body); error = "" }
        else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
    }
    LaunchedEffect(Unit) { load() }
    Column(Modifier.padding(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Databases", fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            IconButton({ scope.launch { load() } }) { Icon(Icons.Default.Refresh, null, tint = Blue) }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(newName, { newName = it }, modifier = Modifier.weight(1f), placeholder = { Text("Database navn", color = Muted) }, textStyle = LocalTextStyle.current.copy(color = White))
            Button({ scope.launch { val r = api.post("/servers/$id/databases", JSONObject().put("name", newName)); if (r.ok) { newName = ""; load() } else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }, Modifier.padding(start = 6.dp)) { Text("Opret") }
        }
        if (error.isNotBlank()) Text(error, color = Red, modifier = Modifier.padding(top = 8.dp))
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 10.dp)) {
            items(databases) { database ->
                SectionCard(database.optString("name", "Database")) {
                    Text("${database.optString("username")} @ ${database.optString("host")}:${database.optInt("port")}", color = Muted)
                    Row {
                        TextButton({ scope.launch { val r = api.get("/servers/$id/databases/${database.optInt("id")}/credentials"); if (r.ok) credentials = JSONObject(r.body) else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Credentials") }
                        TextButton({ scope.launch { val r = api.post("/servers/$id/databases/${database.optInt("id")}/open"); if (r.ok) context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(JSONObject(r.body).getString("url")))) else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("phpMyAdmin") }
                        TextButton({ scope.launch { val r = api.delete("/servers/$id/databases/${database.optInt("id")}"); if (r.ok) load() else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Slet", color = Red) }
                    }
                }
            }
        }
    }
    credentials?.let { value ->
        AlertDialog(
            onDismissRequest = { credentials = null },
            title = { Text("Credentials") },
            text = { Column { Text("Host: ${value.optString("host")}:${value.optInt("port")}"); Text("Database: ${value.optString("name")}"); Text("Username: ${value.optString("username")}"); Text("Password: ${value.optString("password")}") } },
            confirmButton = { TextButton({ credentials = null }) { Text("Luk") } }
        )
    }
}

@Composable
private fun SchedulesTab(api: ApiClient, id: Int) {
    var schedules by remember { mutableStateOf<List<JSONObject>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    suspend fun load() {
        val response = api.get("/servers/$id/schedules")
        if (response.ok) schedules = objectList(response.body) else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
    }
    LaunchedEffect(Unit) { load() }
    Column(Modifier.padding(14.dp)) {
        Row { Text("Schedules", fontSize = 26.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f)); IconButton({ scope.launch { load() } }) { Icon(Icons.Default.Refresh, null, tint = Blue) } }
        if (error.isNotBlank()) Text(error, color = Red)
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(schedules) { schedule ->
                SectionCard(schedule.optString("name", "Schedule")) {
                    Text("${schedule.optString("mode")} • ${schedule.optString("next_run_at", "—")}", color = Muted)
                    Row {
                        TextButton({ scope.launch { val r = api.post("/servers/$id/schedules/${schedule.optInt("id")}/run"); error = if (r.ok) "Startet" else "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Kør nu") }
                        TextButton({ scope.launch { val r = api.delete("/servers/$id/schedules/${schedule.optInt("id")}"); if (r.ok) load() else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Slet", color = Red) }
                    }
                }
            }
        }
    }
}

@Composable
private fun UsersTab(api: ApiClient, id: Int) {
    var users by remember { mutableStateOf<List<JSONObject>>(emptyList()) }
    var error by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    suspend fun load() {
        val response = api.get("/servers/$id/users")
        if (response.ok) users = objectList(response.body) else error = "HTTP ${response.code}: ${errorMessage(response.body)}"
    }
    LaunchedEffect(Unit) { load() }
    Column(Modifier.padding(14.dp)) {
        Text("Subusers", fontSize = 26.sp, fontWeight = FontWeight.Bold)
        if (error.isNotBlank()) Text(error, color = Red)
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(users) { entry ->
                val user = entry.optJSONObject("user")
                SectionCard(user?.optString("name") ?: entry.optString("email", "User")) {
                    val permissions = entry.optJSONArray("permissions") ?: JSONArray()
                    Text((0 until permissions.length()).joinToString(", ") { permissions.optString(it) }, color = Muted)
                    TextButton({ scope.launch { val r = api.delete("/servers/$id/users/${entry.optInt("id")}"); if (r.ok) load() else error = "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Fjern", color = Red) }
                }
            }
        }
    }
}

@Composable
private fun SettingsTab(api: ApiClient, id: Int, server: JSONObject) {
    var name by remember { mutableStateOf(server.optString("name")) }
    var info by remember { mutableStateOf("") }
    val scope = rememberCoroutineScope()
    Column(Modifier.verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Settings", fontSize = 26.sp, fontWeight = FontWeight.Bold)
        NodexaField(name, { name = it }, "Server name")
        Button({ scope.launch { val r = api.put("/servers/$id", JSONObject().put("name", name)); info = if (r.ok) "Gemt" else "HTTP ${r.code}: ${errorMessage(r.body)}" } }) { Text("Gem") }
        SectionCard("Danger zone") {
            Button({ scope.launch { val r = api.post("/servers/$id/reinstall"); info = if (r.ok) "Reinstall startet" else "HTTP ${r.code}: ${errorMessage(r.body)}" } }, colors = ButtonDefaults.buttonColors(containerColor = Red)) { Text("Reinstall") }
        }
        if (info.isNotBlank()) Text(info, color = if (info == "Gemt") Green else Muted)
    }
}
