{if $childrenLayers}
  {if $layerId != 'root'}
  <a href="javascript:shift('id{$nodeId}');" id="xid{$nodeId}" 
  class="lk">{if $groupFolded}+{else}-{/if}</a> <input
  type="checkbox" name="layers[]" value="{$layerId}" id="in{$nodeId}"
  onclick="javascript:updateChecked({$nodeId});" {if $layerChecked}checked="checked"{/if} />
  {if $layerLink}<a href="{$layerLink}" target="_blank" 
  title="{t}more info on{/t} {$layerLabel}">{/if}
  {$layerLabel}
  {if $layerLink}</a>{/if}
  
  <br />
  <div class="{if $groupFolded}nov{else}v{/if}" id="id{$nodeId}">
  {/if}
  {foreach from=$childrenLayers item=layer}
  {$layer}
  {/foreach}
  {if $layerId != 'root'}
  </div>
  {/if}
{else}
<span class="lk">-</span>
  {if $layerClassName != 'LayerClass'}
  <input type="checkbox" name="layers[]" value="{$layerId}" id="in{$nodeId}"
  onclick="javascript:updateChecked({$nodeId});" {if $layerChecked}checked="checked"{/if} />
  {/if}
  {if $layerLink}<a href="{$layerLink}" target="_blank"
  title="{t}more info on{/t} {$layerLabel}">{/if}
  {$layerLabel}
  {if $layerLink}</a>{/if}
  <br />
{/if}
