package dk.nodexa.app

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.webkit.CookieManager
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback

class MainActivity : ComponentActivity() {
    companion object { private const val PANEL = "https://panel.revivegaming.org" }
    private lateinit var root: FrameLayout
    private var browser: WebView? = null
    private var currentTab = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.statusBarColor = c("#090C12"); window.navigationBarColor = c("#090C12")
        root = FrameLayout(this).apply { setBackgroundColor(c("#090C12")) }; setContentView(root); showDashboard()
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() { browser?.let { if (it.canGoBack()) { it.goBack(); return } }; if (browser != null) showDashboard() else { isEnabled = false; onBackPressedDispatcher.onBackPressed() } }
        })
    }

    private fun showDashboard() {
        browser?.destroy(); browser = null; currentTab = 0; root.removeAllViews()
        val shell = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setBackgroundColor(c("#090C12")) }
        shell.addView(topBar("Nodexa", "Game server management"))
        val scroll = ScrollView(this).apply { isFillViewport = true }
        val body = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(18), dp(18), dp(18), dp(24)) }
        body.addView(TextView(this).apply { text="OVERSIGT"; textSize=12f; setTextColor(c("#7E8A9D")); setTypeface(typeface, Typeface.BOLD) })
        body.addView(TextView(this).apply { text="Dine servere"; textSize=29f; setTextColor(Color.WHITE); setTypeface(typeface, Typeface.BOLD); setPadding(0,dp(4),0,dp(5)) })
        body.addView(TextView(this).apply { text="Administrér alt fra én hurtig mobilapp."; textSize=15f; setTextColor(c("#8D98A9")); setPadding(0,0,0,dp(20)) })
        body.addView(serverCard("Nodexa Server", "Tryk for at åbne serverpanelet", "ONLINE", "$PANEL/client")); body.addView(space(14))
        body.addView(TextView(this).apply { text="Hurtige handlinger"; textSize=18f; setTextColor(Color.WHITE); setTypeface(typeface,Typeface.BOLD); setPadding(0,dp(8),0,dp(12)) })
        body.addView(actionRow("⌘","Konsol","Live konsol og kommandoer") { openPanel("$PANEL/server","Konsol") })
        body.addView(actionRow("▤","Filer","Administrér serverfiler") { openPanel("$PANEL/server","Filer") })
        body.addView(actionRow("↻","Backups","Opret og gendan backups") { openPanel("$PANEL/server","Backups") })
        body.addView(actionRow("▦","Databaser","Administrér databaser") { openPanel("$PANEL/server","Databaser") })
        scroll.addView(body); shell.addView(scroll, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0,1f)); shell.addView(bottomBar()); root.addView(shell)
    }

    private fun topBar(name:String, sub:String):View {
        val bar=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL; gravity=Gravity.CENTER_VERTICAL; setPadding(dp(18),dp(12),dp(14),dp(12)); setBackgroundColor(c("#0D1119")) }
        val logo=TextView(this).apply { text="N"; gravity=Gravity.CENTER; textSize=19f; setTextColor(Color.WHITE); setTypeface(typeface,Typeface.BOLD); background=round("#6C5CE7",13) }; bar.addView(logo,LinearLayout.LayoutParams(dp(44),dp(44)))
        val labels=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL; setPadding(dp(12),0,0,0) }; labels.addView(TextView(this).apply { text=name; textSize=17f; setTextColor(Color.WHITE); setTypeface(typeface,Typeface.BOLD) }); labels.addView(TextView(this).apply { text=sub; textSize=11f; setTextColor(c("#778397")) }); bar.addView(labels,LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f))
        bar.addView(TextView(this).apply { text="●"; gravity=Gravity.CENTER; textSize=13f; setTextColor(c("#42D392")); background=round("#151B26",13) },LinearLayout.LayoutParams(dp(42),dp(42))); return bar
    }

    private fun serverCard(name:String,description:String,status:String,url:String):View = LinearLayout(this).apply {
        orientation=LinearLayout.VERTICAL; setPadding(dp(18),dp(18),dp(18),dp(18)); background=bordered("#101722","#222D3E",18)
        val header=LinearLayout(this@MainActivity).apply { orientation=LinearLayout.HORIZONTAL; gravity=Gravity.CENTER_VERTICAL }
        header.addView(TextView(this@MainActivity).apply { text="▣"; gravity=Gravity.CENTER; textSize=22f; setTextColor(c("#9B8CFF")); background=round("#201C3A",13) },LinearLayout.LayoutParams(dp(48),dp(48)))
        val info=LinearLayout(this@MainActivity).apply { orientation=LinearLayout.VERTICAL; setPadding(dp(12),0,0,0) }; info.addView(TextView(this@MainActivity).apply { text=name; textSize=18f; setTextColor(Color.WHITE); setTypeface(typeface,Typeface.BOLD) }); info.addView(TextView(this@MainActivity).apply { text=description; textSize=12f; setTextColor(c("#7F8B9E")) }); header.addView(info,LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f)); header.addView(TextView(this@MainActivity).apply { text=status; textSize=10f; gravity=Gravity.CENTER; setTextColor(c("#62E6A7")); setTypeface(typeface,Typeface.BOLD); background=round("#123328",10); setPadding(dp(10),dp(7),dp(10),dp(7)) }); addView(header); addView(space(18))
        val stats=LinearLayout(this@MainActivity).apply { orientation=LinearLayout.HORIZONTAL }; stats.addView(stat("CPU","—")); stats.addView(stat("RAM","—")); stats.addView(stat("DISK","—")); addView(stats); addView(space(16))
        val power=LinearLayout(this@MainActivity).apply { orientation=LinearLayout.HORIZONTAL }; power.addView(powerButton("▶","Start"){openPanel(url,"Server")}); power.addView(powerButton("↻","Genstart"){openPanel(url,"Server")}); power.addView(powerButton("■","Stop"){openPanel(url,"Server")}); addView(power); setOnClickListener { openPanel(url,name) }
    }

    private fun stat(label:String,value:String):View=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL; setPadding(dp(12),dp(10),dp(12),dp(10)); background=round("#0B1018",11); addView(TextView(this@MainActivity).apply{text=value;textSize=16f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD)}); addView(TextView(this@MainActivity).apply{text=label;textSize=9f;setTextColor(c("#68758A"))}); layoutParams=LinearLayout.LayoutParams(0,dp(58),1f).apply{marginEnd=dp(7)} }
    private fun powerButton(icon:String,label:String,action:()->Unit):View=TextView(this).apply { text="$icon  $label";textSize=11f;gravity=Gravity.CENTER;setTextColor(c("#C6CFDC"));background=bordered("#151C28","#273247",11);setOnClickListener{action()};layoutParams=LinearLayout.LayoutParams(0,dp(43),1f).apply{marginEnd=dp(7)} }
    private fun actionRow(icon:String,name:String,desc:String,click:()->Unit):View=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL;setPadding(dp(14),dp(12),dp(14),dp(12));background=bordered("#0F151F","#1C2635",14);addView(TextView(this@MainActivity).apply{text=icon;gravity=Gravity.CENTER;textSize=20f;setTextColor(c("#9C8DFF"));background=round("#1B1932",11)},LinearLayout.LayoutParams(dp(43),dp(43)));val txt=LinearLayout(this@MainActivity).apply{orientation=LinearLayout.VERTICAL;setPadding(dp(12),0,0,0)};txt.addView(TextView(this@MainActivity).apply{text=name;textSize=14f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD)});txt.addView(TextView(this@MainActivity).apply{text=desc;textSize=11f;setTextColor(c("#778397"))});addView(txt,LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f));addView(TextView(this@MainActivity).apply{text="›";textSize=25f;setTextColor(c("#68758A"))});setOnClickListener{click()};layoutParams=LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(72)).apply{bottomMargin=dp(9)} }

    private fun bottomBar():View { val bar=LinearLayout(this).apply{orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER;setPadding(dp(7),dp(7),dp(7),dp(9));setBackgroundColor(c("#0D1119"))}; listOf("⌂\nHjem","▣\nServere","＋\nBestil","☰\nMere").forEachIndexed{i,label->bar.addView(TextView(this).apply{text=label;textSize=11f;gravity=Gravity.CENTER;setTextColor(if(i==currentTab)c("#C7BEFF") else c("#788397"));background=if(i==currentTab)round("#211D3D",13) else round("#0D1119",13);setOnClickListener{when(i){0->showDashboard();1->openPanel("$PANEL/client","Servere");2->openPanel("$PANEL/client/order","Bestil");else->openPanel(PANEL,"Nodexa")}}},LinearLayout.LayoutParams(0,dp(57),1f))};return bar }

    private fun openPanel(url:String,page:String) {
        root.removeAllViews(); currentTab=if(url.contains("order"))2 else 1
        val shell=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(c("#090C12"))};shell.addView(topBar(page,"Nodexa"))
        val web=WebView(this).apply { setBackgroundColor(c("#090C12"));settings.javaScriptEnabled=true;settings.domStorageEnabled=true;settings.userAgentString="${settings.userAgentString} NodexaAndroid/3.0";webViewClient=object:WebViewClient(){override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest):Boolean{val uri=request.url;if(uri.scheme=="http"||uri.scheme=="https")return false;return runCatching{startActivity(Intent(Intent.ACTION_VIEW,uri));true}.getOrDefault(false)}} }
        CookieManager.getInstance().apply { setAcceptCookie(true); setAcceptThirdPartyCookies(web,true) }
        web.loadUrl(url); browser=web;shell.addView(web,LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0,1f));shell.addView(bottomBar());root.addView(shell)
    }

    private fun space(h:Int)=View(this).apply{layoutParams=LinearLayout.LayoutParams(1,dp(h))}
    private fun c(hex:String)=Color.parseColor(hex)
    private fun dp(v:Int)=(v*resources.displayMetrics.density).toInt()
    private fun round(color:String,radius:Int)=GradientDrawable().apply{setColor(c(color));cornerRadius=dp(radius).toFloat()}
    private fun bordered(bg:String,stroke:String,radius:Int)=GradientDrawable().apply{setColor(c(bg));setStroke(dp(1),c(stroke));cornerRadius=dp(radius).toFloat()}
    override fun onDestroy(){browser?.destroy();super.onDestroy()}
}
