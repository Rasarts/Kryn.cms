{foreach from=$navi.links item=link}
  {assign var="go" value=1}
  {assign var="title" value=$link.title}

  {if $link.properties}
    {if $link.properties.sitemap && $link.properties.sitemap.hideInSitemap eq 1}
        {assign var="go" value=0}
    {/if}
    {if $link.properties.sitemap && $link.properties.sitemap.alternativeTitle ne ""}
        {assign var="title" value=$link.properties.sitemap.alternativeTitle}
    {/if}
  {/if}
  {if $go eq 1 }
      <div>
        » {if $link.type ne 2}<a title="{$title}" href="{$link|@realUrl}/">{$title}</a>{else}{$title}{/if}<br />
        {if $link.links|@count>0}
           <div class="sitemap-sublinks">
           {include file='sitemap/navis/default_navi.tpl' navi=$link}
           </div>
        {/if}
      </div>
  {/if}
{/foreach}