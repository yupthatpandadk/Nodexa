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
        window.statusBarColor = c("#080A0F"); window.navigationBarColor = c("#080A0F")
        root = FrameLayout(this).apply { setBackgroundColor(c("#080A0F")) }; setContentView(root); showDashboard()
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() { browser?.let { if (it.canGoBack()) { it.goBack(); return } }; if (browser != null) showDashboard() else { isEnabled=false; onBackPressedDispatcher.onBackPressed() } }
        })
    }

    private fun showDashboard() {
        browser?.destroy(); browser=null; currentTab=0; root.removeAllViews()
        val shell=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL; setBackgroundColor(c("#080A0F")) }
        shell.addView(topBar("Dashboard","Nodexa"))
        val scroll=ScrollView(this).apply { isFillViewport=true }
        val body=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL; setPadding(dp(16),dp(14),dp(16),dp(28)) }
        body.addView(sectionLabel("SERVER OVERVIEW"))
        body.addView(TextView(this).apply { text="Welcome back"; textSize=26f; setTextColor(Color.WHITE); setTypeface(typeface,Typeface.BOLD); setPadding(0,dp(5),0,dp(3)) })
        body.addView(TextView(this).apply { text="Manage your servers, files and resources."; textSize=13f; setTextColor(c("#7D8597")); setPadding(0,0,0,dp(18)) })

        val overview=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL }
        overview.addView(summaryCard("1","SERVERS","#8B7CFF")); overview.addView(summaryCard("1","ONLINE","#36D399")); overview.addView(summaryCard("0","OFFLINE","#F87171")); body.addView(overview); body.addView(space(16))
        body.addView(serverCard("Nodexa Server","panel.revivegaming.org","ONLINE","$PANEL/client")); body.addView(space(22))
        body.addView(sectionLabel("QUICK ACCESS")); body.addView(space(9))
        val grid1=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL }; grid1.addView(tile("⌘","Console","Live commands"){openPanel("$PANEL/server","Console")}); grid1.addView(tile("▤","Files","File manager"){openPanel("$PANEL/server","Files")}); body.addView(grid1); body.addView(space(10))
        val grid2=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL }; grid2.addView(tile("↻","Backups","Snapshots"){openPanel("$PANEL/server","Backups")}); grid2.addView(tile("▦","Databases","MySQL access"){openPanel("$PANEL/server","Databases")}); body.addView(grid2)
        scroll.addView(body); shell.addView(scroll,LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0,1f)); shell.addView(bottomBar()); root.addView(shell)
    }

    private fun topBar(title:String,sub:String):View {
        val bar=LinearLayout(this).apply { orientation=LinearLayout.HORIZONTAL; gravity=Gravity.CENTER_VERTICAL; setPadding(dp(16),dp(11),dp(14),dp(11)); setBackgroundColor(c("#0C0F16")) }
        val logo=TextView(this).apply { text="N";gravity=Gravity.CENTER;textSize=18f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD);background=round("#6757E8",11) };bar.addView(logo,LinearLayout.LayoutParams(dp(40),dp(40)))
        val labels=LinearLayout(this).apply { orientation=LinearLayout.VERTICAL;setPadding(dp(11),0,0,0) };labels.addView(TextView(this).apply{text=title;textSize=16f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD)});labels.addView(TextView(this).apply{text=sub;textSize=10f;setTextColor(c("#70798B"))});bar.addView(labels,LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f))
        bar.addView(TextView(this).apply{text="●";gravity=Gravity.CENTER;textSize=11f;setTextColor(c("#36D399"));background=bordered("#121722","#202735",10)},LinearLayout.LayoutParams(dp(38),dp(38)));return bar
    }

    private fun sectionLabel(t:String)=TextView(this).apply{text=t;textSize=10f;letterSpacing=.12f;setTextColor(c("#687286"));setTypeface(typeface,Typeface.BOLD)}

    private fun summaryCard(value:String,label:String,accent:String):View=LinearLayout(this).apply {
        orientation=LinearLayout.VERTICAL;setPadding(dp(12),dp(12),dp(10),dp(11));background=bordered("#0E131C","#1B2431",13)
        addView(TextView(this@MainActivity).apply{text=value;textSize=21f;setTextColor(c(accent));setTypeface(typeface,Typeface.BOLD)});addView(TextView(this@MainActivity).apply{text=label;textSize=9f;setTextColor(c("#707B8E"));setTypeface(typeface,Typeface.BOLD)});layoutParams=LinearLayout.LayoutParams(0,dp(68),1f).apply{marginEnd=dp(8)}
    }

    private fun serverCard(name:String,host:String,status:String,url:String):View=LinearLayout(this).apply {
        orientation=LinearLayout.VERTICAL;setPadding(dp(15),dp(15),dp(15),dp(15));background=bordered("#0E141E","#202A39",15)
        val h=LinearLayout(this@MainActivity).apply{orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL};h.addView(TextView(this@MainActivity).apply{text="▣";gravity=Gravity.CENTER;textSize=20f;setTextColor(c("#A397FF"));background=round("#1B1930",11)},LinearLayout.LayoutParams(dp(43),dp(43)))
        val i=LinearLayout(this@MainActivity).apply{orientation=LinearLayout.VERTICAL;setPadding(dp(11),0,0,0)};i.addView(TextView(this@MainActivity).apply{text=name;textSize=16f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD)});i.addView(TextView(this@MainActivity).apply{text=host;textSize=10f;setTextColor(c("#6E788A"))});h.addView(i,LinearLayout.LayoutParams(0,ViewGroup.LayoutParams.WRAP_CONTENT,1f));h.addView(TextView(this@MainActivity).apply{text="● $status";textSize=9f;setTextColor(c("#4ADEA3"));setTypeface(typeface,Typeface.BOLD);background=round("#10291F",9);setPadding(dp(8),dp(6),dp(8),dp(6))});addView(h);addView(space(14))
        val stats=LinearLayout(this@MainActivity).apply{orientation=LinearLayout.HORIZONTAL};stats.addView(stat("CPU","0%"));stats.addView(stat("MEMORY","—"));stats.addView(stat("DISK","—"));addView(stats);addView(space(13))
        val actions=LinearLayout(this@MainActivity).apply{orientation=LinearLayout.HORIZONTAL};actions.addView(powerButton("▶","Start","#38D39F"){openPanel(url,"Server")});actions.addView(powerButton("↻","Restart","#A397FF"){openPanel(url,"Server")});actions.addView(powerButton("■","Stop","#F87171"){openPanel(url,"Server")});addView(actions);setOnClickListener{openPanel(url,name)}
    }

    private fun stat(label:String,value:String):View=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setPadding(dp(10),dp(8),dp(8),dp(8));background=round("#090E15",9);addView(TextView(this@MainActivity).apply{text=value;textSize=14f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD)});addView(TextView(this@MainActivity).apply{text=label;textSize=8f;setTextColor(c("#626D80"));setTypeface(typeface,Typeface.BOLD)});layoutParams=LinearLayout.LayoutParams(0,dp(52),1f).apply{marginEnd=dp(6)}}
    private fun powerButton(icon:String,label:String,color:String,action:()->Unit):View=TextView(this).apply{text="$icon  $label";textSize=10f;gravity=Gravity.CENTER;setTextColor(c(color));background=bordered("#101721","#242E3D",9);setOnClickListener{action()};layoutParams=LinearLayout.LayoutParams(0,dp(40),1f).apply{marginEnd=dp(6)}}
    private fun tile(icon:String,name:String,desc:String,action:()->Unit):View=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setPadding(dp(14),dp(14),dp(14),dp(13));background=bordered("#0E141D","#1E2735",13);addView(TextView(this@MainActivity).apply{text=icon;textSize=21f;setTextColor(c("#9B8CFF"))});addView(TextView(this@MainActivity).apply{text=name;textSize=14f;setTextColor(Color.WHITE);setTypeface(typeface,Typeface.BOLD);setPadding(0,dp(8),0,dp(2))});addView(TextView(this@MainActivity).apply{text=desc;textSize=10f;setTextColor(c("#707B8E"))});setOnClickListener{action()};layoutParams=LinearLayout.LayoutParams(0,dp(105),1f).apply{marginEnd=dp(9)}}

    private fun bottomBar():View { val bar=LinearLayout(this).apply{orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER;setPadding(dp(6),dp(6),dp(6),dp(8));setBackgroundColor(c("#0B0F15"))};listOf("⌂\nHome","▣\nServers","＋\nDeploy","☰\nMore").forEachIndexed{i,label->bar.addView(TextView(this).apply{text=label;textSize=10f;gravity=Gravity.CENTER;setTextColor(if(i==currentTab)c("#B6AAFF") else c("#697487"));background=if(i==currentTab)round("#19162C",10) else round("#0B0F15",10);setOnClickListener{when(i){0->showDashboard();1->openPanel("$PANEL/client","Servers");2->openPanel("$PANEL/client/order","Deploy");else->openPanel(PANEL,"Nodexa")}}},LinearLayout.LayoutParams(0,dp(54),1f))};return bar }

    private fun openPanel(url:String,page:String){root.removeAllViews();currentTab=if(url.contains("order"))2 else 1;val shell=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setBackgroundColor(c("#080A0F"))};shell.addView(topBar(page,"Nodexa"));val web=WebView(this).apply{setBackgroundColor(c("#080A0F"));settings.javaScriptEnabled=true;settings.domStorageEnabled=true;settings.userAgentString="${settings.userAgentString} NodexaAndroid/3.1";webViewClient=object:WebViewClient(){override fun shouldOverrideUrlLoading(view:WebView,request:WebResourceRequest):Boolean{val uri=request.url;if(uri.scheme=="http"||uri.scheme=="https")return false;return runCatching{startActivity(Intent(Intent.ACTION_VIEW,uri));true}.getOrDefault(false)}}};CookieManager.getInstance().apply{setAcceptCookie(true);setAcceptThirdPartyCookies(web,true)};web.loadUrl(url);browser=web;shell.addView(web,LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0,1f));shell.addView(bottomBar());root.addView(shell)}
    private fun space(h:Int)=View(this).apply{layoutParams=LinearLayout.LayoutParams(1,dp(h))};private fun c(hex:String)=Color.parseColor(hex);private fun dp(v:Int)=(v*resources.displayMetrics.density).toInt();private fun round(color:String,radius:Int)=GradientDrawable().apply{setColor(c(color));cornerRadius=dp(radius).toFloat()};private fun bordered(bg:String,stroke:String,radius:Int)=GradientDrawable().apply{setColor(c(bg));setStroke(dp(1),c(stroke));cornerRadius=dp(radius).toFloat()};override fun onDestroy(){browser?.destroy();super.onDestroy()}
}
