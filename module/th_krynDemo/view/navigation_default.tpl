<ol style="list-style-type: none;">
    {foreach from=$navi._children item=link}
        <li>» <a class="{if $link|@active} active{/if}" title="{$link.title}" href="{$link|@realUrl}/">{$link.title}</a></li>
    {/foreach}
</ol>
