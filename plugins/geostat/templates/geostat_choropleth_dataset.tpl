<h4>{t}Choropleth dataset configuration{/t}</h4>

<div id="geostat_choropleth_univar">
<p>{t}Number of data :{/t} {$geostatChoroplethNbVal} </p>
<p>{t}Min. :{/t} {$geostatChoroplethMin} &emsp; 
{t}Max. :{/t} {$geostatChoroplethMax}  </p>
<p>{t}Mean :{/t} {$geostatChoroplethMean} </p>
<p>{t}Std Div :{/t} {$geostatChoroplethStdDev} </p>
</div>

<p>
<label for="geostatChoroplethNbClasses">{t}Number of classes{/t}</label> 
<input name="geostatChoroplethNbClasses" type="text" size="3"
value="{$geostatChoroplethNbBins}" onblur="doSubmit()"/>
</p>

<p>
<label for="geostatChoroplethNbClasses">{t}Classification method{/t}</label> 
<select name="geostatChoroplethClassifMethod" onchange="doSubmit()">
{html_options options=$geostatChoroplethClassifMethod 
selected=$geostatChoroplethClassifMethodSelected}
</select>
</p>

<p>{t}Class limits{/t}</p>
{foreach from=$geostatChoroplethBounds item=bound}
    <input name="geostatChoroplethBounds[]" type="text" size="5" 
    value="{$bound}"/><br/>
{/foreach}
<input type="submit" value="{t}Apply{/t}" onclick="javascript:return CartoWeb.trigger('Geostat.UpdateAll', 'doSubmit()');">