package dk.nodexa.app

import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.Uri
import android.os.Bundle
import android.os.Environment
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.webkit.CookieManager
import android.webkit.DownloadListener
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts

class MainActivity : ComponentActivity() {
    companion object {
        private const val PANEL_URL = "https://panel.revivegaming.org"
        private const val APP_UA = "NodexaAndroid/2.0"
    }

    private lateinit var webView: WebView
    private lateinit var pageProgress: ProgressBar
    private lateinit var title: TextView
    private lateinit var subtitle: TextView
    private lateinit var offline: TextView
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private val navItems = mutableListOf<TextView>()

    private val filePicker = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        fileCallback?.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data))
        fileCallback = null
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.statusBarColor = Color.rgb(7, 10, 16)
        window.navigationBarColor = Color.rgb(7, 10, 16)

        webView = createWebView()
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(Color.rgb(7, 10, 16))
        }
        root.addView(createHeader())
        pageProgress = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply {
            max = 100
            progress = 0
            visibility = View.GONE
        }
        root.addView(pageProgress, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(2)))

        val content = FrameLayout(this)
        content.addView(webView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        offline = TextView(this).apply {
            text = "Ingen forbindelse\nTjek dit netværk og tryk Opdater"
            setTextColor(Color.rgb(225, 231, 239)); textSize = 16f; gravity = Gravity.CENTER
            visibility = View.GONE
            setBackgroundColor(Color.rgb(7, 10, 16))
            setOnClickListener { webView.reload() }
        }
        content.addView(offline, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        root.addView(content, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f))
        root.addView(createBottomNavigation())
        setContentView(root)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) webView.goBack() else { isEnabled = false; onBackPressedDispatcher.onBackPressed() }
            }
        })
        if (savedInstanceState == null) webView.loadUrl(PANEL_URL) else webView.restoreState(savedInstanceState)
    }

    private fun createHeader(): View {
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL; gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(18), dp(10), dp(12), dp(10))
            setBackgroundColor(Color.rgb(10, 15, 23))
        }
        val brand = TextView(this).apply {
            text = "N"; gravity = Gravity.CENTER; textSize = 18f; setTextColor(Color.WHITE); setTypeface(typeface, Typeface.BOLD)
            background = rounded(Color.rgb(92, 75, 255), 14)
        }
        row.addView(brand, LinearLayout.LayoutParams(dp(42), dp(42)))
        val textBox = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(12), 0, 0, 0) }
        title = TextView(this).apply { text = "Nodexa"; textSize = 16f; setTextColor(Color.WHITE); setTypeface(typeface, Typeface.BOLD) }
        subtitle = TextView(this).apply { text = "Server Management"; textSize = 11f; setTextColor(Color.rgb(137, 148, 166)) }
        textBox.addView(title); textBox.addView(subtitle)
        row.addView(textBox, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        val refresh = TextView(this).apply {
            text = "↻"; textSize = 24f; gravity = Gravity.CENTER; setTextColor(Color.rgb(174, 184, 201)); background = rounded(Color.rgb(17, 24, 36), 14)
            setOnClickListener { webView.reload() }
        }
        row.addView(refresh, LinearLayout.LayoutParams(dp(42), dp(42)))
        return row
    }

    private fun createWebView(): WebView = WebView(this).apply web@{
        setBackgroundColor(Color.rgb(7, 10, 16))
        settings.apply {
            javaScriptEnabled = true; domStorageEnabled = true; databaseEnabled = true
            cacheMode = WebSettings.LOAD_DEFAULT; allowFileAccess = true; allowContentAccess = true
            mediaPlaybackRequiresUserGesture = false; builtInZoomControls = false; displayZoomControls = false; setSupportZoom(false)
            userAgentString = "$userAgentString $APP_UA"
        }
        CookieManager.getInstance().apply { setAcceptCookie(true); setAcceptThirdPartyCookies(this@web, true) }
        webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url
                if (uri.scheme == "http" || uri.scheme == "https") return false
                return runCatching { startActivity(Intent(Intent.ACTION_VIEW, uri)); true }.getOrDefault(false)
            }
            override fun onPageStarted(view: WebView, url: String, favicon: android.graphics.Bitmap?) {
                pageProgress.visibility = View.VISIBLE; offline.visibility = View.GONE; updatePageLabel(url)
            }
            override fun onPageFinished(view: WebView, url: String) {
                pageProgress.visibility = View.GONE; updatePageLabel(url); injectAppTheme(view)
            }
            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: android.webkit.WebResourceError) {
                if (request.isForMainFrame && !isOnline()) { pageProgress.visibility = View.GONE; offline.visibility = View.VISIBLE }
            }
        }
        webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                pageProgress.progress = newProgress
                pageProgress.visibility = if (newProgress >= 100) View.GONE else View.VISIBLE
            }
            override fun onShowFileChooser(webView: WebView?, cb: ValueCallback<Array<Uri>>?, params: FileChooserParams?): Boolean {
                fileCallback?.onReceiveValue(null); fileCallback = cb
                val intent = runCatching { params?.createIntent() }.getOrNull() ?: Intent(Intent.ACTION_GET_CONTENT).apply { type = "*/*"; addCategory(Intent.CATEGORY_OPENABLE) }
                return runCatching { filePicker.launch(intent); true }.getOrElse { fileCallback = null; false }
            }
        }
        setDownloadListener(DownloadListener { url, userAgent, disposition, mime, _ ->
            runCatching {
                val request = DownloadManager.Request(Uri.parse(url)).apply {
                    setMimeType(mime); addRequestHeader("User-Agent", userAgent)
                    CookieManager.getInstance().getCookie(url)?.let { addRequestHeader("Cookie", it) }
                    setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                    setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, android.webkit.URLUtil.guessFileName(url, disposition, mime))
                }
                (getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager).enqueue(request)
                Toast.makeText(this@MainActivity, "Download startet", Toast.LENGTH_SHORT).show()
            }.onFailure { Toast.makeText(this@MainActivity, "Download fejlede", Toast.LENGTH_SHORT).show() }
        })
    }

    private fun createBottomNavigation(): View {
        val bar = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL; gravity = Gravity.CENTER; setPadding(dp(8), dp(7), dp(8), dp(8))
            background = rounded(Color.rgb(10, 15, 23), 0)
        }
        listOf(
            Triple("⌂\nHjem", "$PANEL_URL/", 0),
            Triple("▣\nServere", "$PANEL_URL/client", 1),
            Triple("＋\nBestil", "$PANEL_URL/client/order", 2),
            Triple("☰\nMenu", "menu", 3)
        ).forEach { (label, url, index) ->
            val item = navButton(label) { if (url == "menu") openWebsiteMenu() else webView.loadUrl(url); selectNav(index) }
            navItems.add(item); bar.addView(item)
        }
        selectNav(0)
        return bar
    }

    private fun navButton(label: String, click: () -> Unit) = TextView(this).apply {
        text = label; textSize = 11f; gravity = Gravity.CENTER; setTextColor(Color.rgb(137, 148, 166)); setPadding(dp(3), dp(5), dp(3), dp(5))
        background = rounded(Color.TRANSPARENT, 14); setOnClickListener { click() }
        layoutParams = LinearLayout.LayoutParams(0, dp(58), 1f).apply { marginStart = dp(2); marginEnd = dp(2) }
    }

    private fun selectNav(index: Int) = navItems.forEachIndexed { i, v ->
        v.setTextColor(if (i == index) Color.rgb(197, 190, 255) else Color.rgb(137, 148, 166))
        v.background = rounded(if (i == index) Color.rgb(27, 25, 58) else Color.TRANSPARENT, 14)
    }

    private fun updatePageLabel(url: String) {
        val label = when {
            url.contains("server", true) -> "Server"
            url.contains("billing", true) -> "Billing"
            url.contains("ticket", true) -> "Support"
            url.contains("order", true) -> "Bestil"
            url.contains("admin", true) -> "Administration"
            else -> "Dashboard"
        }
        title.text = label; subtitle.text = "Nodexa • Server Management"
    }

    private fun openWebsiteMenu() {
        webView.evaluateJavascript("""(function(){const s=['button[aria-label*=menu i]','button[title*=menu i]','header button','nav button'];for(const q of s){const x=[...document.querySelectorAll(q)].find(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0});if(x){x.click();return 'ok'}}return 'no'})()""") { r ->
            if (r == "\"no\"") Toast.makeText(this, "Menuen kunne ikke findes", Toast.LENGTH_SHORT).show()
        }
    }

    private fun injectAppTheme(view: WebView) {
        val js = """
            (function(){
              if(document.getElementById('nodexa-native-v2')) return;
              const s=document.createElement('style');s.id='nodexa-native-v2';s.textContent=`
              :root{--app-accent:#786cff!important} html,body{background:#070a10!important}
              @media(max-width:760px){body{overscroll-behavior-y:none} main,.content,.container{max-width:100%!important} .card,[class*=card]{border-radius:16px!important} table{font-size:12px!important} button,a[role=button],input[type=submit]{min-height:44px!important;border-radius:12px!important} input,textarea,select{font-size:16px!important;min-height:44px!important} }
              input,textarea,select{color:#f4f7fb!important;-webkit-text-fill-color:#f4f7fb!important} input::placeholder,textarea::placeholder{color:#778399!important;-webkit-text-fill-color:#778399!important}
              `;document.head.appendChild(s);
            })();
        """.trimIndent()
        view.evaluateJavascript(js, null)
    }

    private fun isOnline(): Boolean {
        val cm = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(network) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun rounded(color: Int, radius: Int) = GradientDrawable().apply { setColor(color); cornerRadius = dp(radius).toFloat() }
    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()
    override fun onSaveInstanceState(outState: Bundle) { webView.saveState(outState); super.onSaveInstanceState(outState) }
    override fun onDestroy() { fileCallback?.onReceiveValue(null); webView.stopLoading(); webView.destroy(); super.onDestroy() }
}
