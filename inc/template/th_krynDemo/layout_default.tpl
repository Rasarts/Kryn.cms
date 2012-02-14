{addCss file="th_krynDemo/base.css"}

<div class="header">
    <div class="wrapper">
        <div class="header-top">
            {navigation level="1" template="th_krynDemo/navigation_top.tpl"}
        </div>
        
        <div class="header-logo">
            <a href="{$path}">
                <img src="{resizeImage file=$themeProperties.logo dimension="90x90"}" align="left" />
                <span class="header-logo-title">{$themeProperties.title}</span><br />
                <span class="header-logo-slogan">{$themeProperties.slogan}</span>
            </a>
        </div>
        
        <div class="header-subnavi">
            {navigation level="2" template="th_krynDemo/navigation_subnavi.tpl"}
        </div>
        
        <div class="header-search">
            <form action="{$themeProperties.search_page|realUrl}" method="get">
                <input type="text" name="q" value="[[Keyword ...]]" onfocus="if(this.value == '[[Keyword ...]]')this.value=''" onblur="if(this.value=='')this.value='[[Keyword ...]]'"/>
                <input type="submit" class="submit" value="{tc "searchButton" "Search"}" />
                <input type="hidden" name="searchDo" value="1" />
            </form>
        </div>
    </div>
</div>

<div class="content">
    <div class="wrapper">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td valign="top">
                    <div class="content-main">
                        <div class="content-main-padding">

                            {navigation id="history" template="th_krynDemo/navigation_breadcrumb.tpl"}

                                <hr />
                                <div style="position: relative; z-index:123123123;">{krynObject::getFromUrl('object://system_user?fields=rsn,username,groups')|print_r:true}</pre>

                                <hr />
                                <pre>{krynObject::count('news')|print_r:true}</pre>

                                <hr />
                                <pre>{krynObject::getFromUrl('object://news/5?fields=rsn,title,category_rsn,intro')|print_r:true}</pre>

                                <hr />
                                <pre>{krynObject::getFromUrl('news/1,3?fields=rsn,title')|print_r:true}</pre>

                                <hr />
                                <pre>{krynObject::getFromUrl('news?fields=rsn,title,category_rsn&condition=news.rsn>1&orderBy=news.rsn')|print_r:true}</pre>
                                <hr />

                            {slot id="1" name="[[Main content]]" picturedimension="640x1000"}
                        </div>
                    </div>
                </td>
                <td valign="top">
                    {if $admin}
                        <div class="content-sidebar">
                            {slot id="2" name="[[Sidebar]]" assign="sidebar"}
                        </div>
                    {else}
                        {slot id="2" name="[[Sidebar]]" assign="sidebar"}
                        {if $sidebar ne ""}
                            <div class="content-sidebar">
                                {$sidebar}
                            </div>
                        {/if}
                    {/if}
                </td>
            </tr>
        </table>
    
    </div>
</div>


<div class="footer">
    <div class="wrapper">footer-box
        <div class="footer-box">
            <div class="footer-box-padding">
                <table width="100%">
                    <tr>
                        <td valign="top">
                            {if $themeProperties.footer_deposit eq ""}
                                [[Please set "Footer deposit" under Domain » Theme » Kryn Demo]]
                            {else}
                                {page id=$themeProperties.footer_deposit}
                            {/if}
                        </td>
                        <td align="right" valign="top">
                            {navigation id=$themeProperties.footer_navi template="th_krynDemo/navigation_footer.tpl"}
                        </td>
                    </tr>
                </table>
                
            </div>
        </div>
    </div>
</div>