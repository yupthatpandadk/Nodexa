package dk.nodexa.app

import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.os.Environment
import android.webkit.CookieManager
import android.webkit.DownloadListener
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts

class MainActivity : ComponentActivity() {

    companion object {
        private const val PANEL_URL = "https://panel.revivegaming.org"
    }

    private lateinit var webView: WebView
    private var fileCallback: ValueCallback<Array<Uri>>? = null

    private val filePicker = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        val callback = fileCallback ?: return@registerForActivityResult
        callback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data))
        fileCallback = null
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.statusBarColor = Color.rgb(9, 13, 20)
        window.navigationBarColor = Color.rgb(9, 13, 20)

        webView = WebView(this).apply web@{
            setBackgroundColor(Color.rgb(9, 13, 20))

            settings.apply {
                javaScriptEnabled = true
                domStorageEnabled = true
                databaseEnabled = true
                cacheMode = WebSettings.LOAD_DEFAULT
                allowFileAccess = true
                allowContentAccess = true
                mediaPlaybackRequiresUserGesture = false
                builtInZoomControls = false
                displayZoomControls = false
                setSupportZoom(false)
                userAgentString = "$userAgentString NodexaAndroid/1.1"
            }

            CookieManager.getInstance().apply {
                setAcceptCookie(true)
                setAcceptThirdPartyCookies(this@web, true)
            }

            webViewClient = object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                    val uri = request.url
                    val scheme = uri.scheme.orEmpty()

                    if (scheme == "http" || scheme == "https") return false

                    return runCatching {
                        startActivity(Intent(Intent.ACTION_VIEW, uri))
                        true
                    }.getOrDefault(false)
                }

                override fun onPageFinished(view: WebView, url: String) {
                    super.onPageFinished(view, url)
                    injectMobileFixes(view)
                }
            }

            webChromeClient = object : WebChromeClient() {
                override fun onShowFileChooser(
                    webView: WebView?,
                    filePathCallback: ValueCallback<Array<Uri>>?,
                    fileChooserParams: FileChooserParams?
                ): Boolean {
                    fileCallback?.onReceiveValue(null)
                    fileCallback = filePathCallback

                    val intent = runCatching { fileChooserParams?.createIntent() }
                        .getOrNull()
                        ?: Intent(Intent.ACTION_GET_CONTENT).apply {
                            type = "*/*"
                            addCategory(Intent.CATEGORY_OPENABLE)
                        }

                    return runCatching {
                        filePicker.launch(intent)
                        true
                    }.getOrElse {
                        fileCallback?.onReceiveValue(null)
                        fileCallback = null
                        false
                    }
                }
            }

            setDownloadListener(DownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
                runCatching {
                    val request = DownloadManager.Request(Uri.parse(url)).apply {
                        setMimeType(mimeType)
                        addRequestHeader("User-Agent", userAgent)
                        CookieManager.getInstance().getCookie(url)?.let { addRequestHeader("Cookie", it) }
                        setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                        setDestinationInExternalPublicDir(
                            Environment.DIRECTORY_DOWNLOADS,
                            android.webkit.URLUtil.guessFileName(url, contentDisposition, mimeType)
                        )
                    }
                    val manager = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
                    manager.enqueue(request)
                    Toast.makeText(this@MainActivity, "Download startet", Toast.LENGTH_SHORT).show()
                }.onFailure {
                    Toast.makeText(this@MainActivity, "Kunne ikke starte download", Toast.LENGTH_SHORT).show()
                }
            })
        }

        setContentView(webView)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) webView.goBack() else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        if (savedInstanceState == null) webView.loadUrl(PANEL_URL)
        else webView.restoreState(savedInstanceState)
    }

    private fun injectMobileFixes(view: WebView) {
        val js = """
            (function () {
                if (!document.getElementById('nodexa-app-mobile-fixes')) {
                    const style = document.createElement('style');
                    style.id = 'nodexa-app-mobile-fixes';
                    style.textContent = `
                        input, textarea, select {
                            color: #f7fffc !important;
                            -webkit-text-fill-color: #f7fffc !important;
                            caret-color: #52e7b3 !important;
                            font-weight: 500 !important;
                        }
                        input::placeholder, textarea::placeholder {
                            color: rgba(247,255,252,.58) !important;
                            -webkit-text-fill-color: rgba(247,255,252,.58) !important;
                            opacity: 1 !important;
                        }
                        input:-webkit-autofill,
                        input:-webkit-autofill:hover,
                        input:-webkit-autofill:focus {
                            -webkit-text-fill-color: #f7fffc !important;
                            caret-color: #52e7b3 !important;
                            -webkit-box-shadow: 0 0 0 1000px #103d35 inset !important;
                            box-shadow: 0 0 0 1000px #103d35 inset !important;
                        }
                        @media (max-width: 760px) {
                            button, [role="button"], input[type="button"], input[type="submit"] {
                                min-height: 44px !important;
                                padding: 10px 14px !important;
                                line-height: 1.2 !important;
                                font-size: 14px !important;
                            }
                            .nodexa-action-group {
                                display: flex !important;
                                flex-wrap: wrap !important;
                                gap: 10px !important;
                                width: 100% !important;
                            }
                            .nodexa-action-group > button,
                            .nodexa-action-group > a,
                            .nodexa-action-group > [role="button"] {
                                flex: 1 1 110px !important;
                                width: auto !important;
                                min-width: 105px !important;
                                max-width: 100% !important;
                                white-space: normal !important;
                                text-align: center !important;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }

                function improveActionGroups() {
                    if (window.innerWidth > 760) return;
                    const candidates = document.querySelectorAll('button, a[role="button"], input[type="button"], input[type="submit"]');
                    const parents = new Set();
                    candidates.forEach(el => { if (el.parentElement) parents.add(el.parentElement); });

                    parents.forEach(parent => {
                        const direct = Array.from(parent.children).filter(el =>
                            el.matches('button, a[role="button"], input[type="button"], input[type="submit"]')
                        );
                        if (direct.length >= 3) parent.classList.add('nodexa-action-group');
                    });
                }

                improveActionGroups();
                if (!window.__nodexaMobileObserver) {
                    window.__nodexaMobileObserver = new MutationObserver(() => improveActionGroups());
                    window.__nodexaMobileObserver.observe(document.body, { childList: true, subtree: true });
                }
            })();
        """.trimIndent()

        view.evaluateJavascript(js, null)
    }

    override fun onSaveInstanceState(outState: Bundle) {
        webView.saveState(outState)
        super.onSaveInstanceState(outState)
    }

    override fun onDestroy() {
        fileCallback?.onReceiveValue(null)
        fileCallback = null
        webView.stopLoading()
        webView.webChromeClient = null
        webView.webViewClient = WebViewClient()
        webView.destroy()
        super.onDestroy()
    }
}
