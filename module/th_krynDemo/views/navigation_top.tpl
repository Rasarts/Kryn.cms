{foreach from=$navi._children item=link}
   <a class="{if $link|@active} active{/if}" title="{$link.title}" href="{$link|@realUrl}">{$link.url}</a>
{/foreach}
