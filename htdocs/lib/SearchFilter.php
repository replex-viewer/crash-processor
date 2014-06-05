<?php

class SearchFilter
{
    public $os;
    public $chan;
    public $version;
    public $version_min;
    public $version_max;
    public $grid;
    public $region;
    public $gpu;
    public $search_type;
    public $search_for;
    public $signature_id;
    
    public $sort_by;
    public $sort_order;
    public static $sort_keys = array("date", "os", "version", "grid");
    public static $sort_orders = array("asc", "desc");
    public $limit = 50;
    public $offset = 0;
    public $page = 0;
    
    var $fields = array("os", "chan", "version", "version_min", "version_max", "grid", "region", "gpu", "search_type", "search_for", "signature_id", "limit", "offset");
    
    function __construct()
    {
        global $S;
        
        foreach($this->fields as $field)
        {
            if (strlen($_GET[$field]))
            {
                $this->$field = trim($_GET[$field]);
            }
        }
        
        if (!isset($_REQUEST["chan"]))
        {
            $this->chan = $S->persist->chan;
        }
        else
        {
            $S->persist->chan = $_REQUEST["chan"];
        }

        if (in_array($_GET["sort_by"], self::$sort_keys))
        {
            $this->sort_by = $_GET["sort_by"];
        }

        if (in_array($_GET["sort_order"], self::$sort_orders))
        {
            $this->sort_by = $_GET["sort_order"];
        }
        
        if (strlen($_GET["page"]))
        {
            $this->page = $_GET["page"];
        }
    }
    
    function getURLArgs()
    {
        $cond = array();
        foreach($this->fields as $field)
        {
            if ($this->$field)
            {
                $cond[] = $field . "=" . urlencode($this->$field);
            }
        }
        
        if (!count($cond))
        {
            return "";
        }
        else
        {
            return implode("&", $cond);
        }
    }
    
    function getWhere()
    {
        $cond = array();
        if ($this->os) $cond[] = kl_str_sql("os_type=!s", $this->os);
        if ($this->version) $cond[] = kl_str_sql("client_version=!s", $this->version);
        if ($this->version_min) $cond[] = kl_str_sql("client_version_s>=!s", CrashReport::sortableVersion($this->version_min));
        if ($this->version_max) $cond[] = kl_str_sql("client_version_s<=!s", CrashReport::sortableVersion($this->version_max));
        if ($this->chan) $cond[] = kl_str_sql("client_channel=!s", $this->chan);
        if ($this->grid) $cond[] = kl_str_sql("grid=!s", $this->grid);
        if ($this->region) $cond[] = kl_str_sql("region=!s", $this->region);
        if ($this->gpu) $cond[] = kl_str_sql("gpu=!s", $this->gpu);
        if ($this->signature_id) $cond[] = kl_str_sql("signature_id=!s", $this->signature_id);
        
        if ($this->search_for)
        {
            $fmap = array("stack" => "raw_stacktrace", "gpu" => "gpu", "os" => "os", "region" => "region");
            $field = $fmap[$this->search_type];
            $parts = preg_split("/\\s+/", trim($this->search_for));
            foreach($parts as $part)
            {
                if ($part[0] == "-")
                {
                    $cond[] = kl_str_sql("$field not like !s", "%" . substr($part, 1) . "%");
                }
                else
                {
                    $cond[] = kl_str_sql("$field like !s", "%{$part}%");
                }
            }
        }
        
        if (!count($cond)) return "";
        return "where " . implode(" and ", $cond);
    }
    
    function getVersions()
    {
        $ret = array();
        $where = $this->chan ? kl_str_sql("where client_channel=!s", $this->chan) : '';
        $q = "select distinct client_version from reports $where order by client_version desc";
        if (false !== $cached = Memc::getq($q)) return $cached;
        
        if (!$res = DBH::$db->query($q))
        {
            return $ret;
        }
        
        while ($row = DBH::$db->fetchRow($res))
        {
            $ret[] = $row["client_version"];
        }
        
        Memc::setq($q, $ret);
        return $ret;
    }
    
    function getGrids()
    {
        $ret = array();
        $q = "select distinct grid from reports order by grid asc";
        if (false !== $cached = Memc::getq($q)) return $cached;
        
        if (!$res = DBH::$db->query($q))
        {
            return $ret;
        }
        
        while ($row = DBH::$db->fetchRow($res))
        {
            $ret[] = $row["grid"];
        }
        
        Memc::setq($q, $ret);
        return $ret;
    }

    function render()
    {
        $ver = $this->getVersions();
        $grids = $this->getGrids();
?>
<script>
  $(function() {
    $( ".radio" )
    .buttonset()
    .on("change", function() {
       $(this).closest("form").submit();
    });
    
    $("#version").on("change", function(){
        if ($(this)[0].selectedIndex > 0) {
            $("#version_min")[0].selectedIndex = 0;
            $("#version_max")[0].selectedIndex = 0;
        }
        $(this).closest("form").submit();
    });

    $("#version_min").on("change", function(){
        if ($(this)[0].selectedIndex > 0) {
            $("#version")[0].selectedIndex = 0;
        }
        $(this).closest("form").submit();
    });

    $("#version_max").on("change", function(){
        if ($(this)[0].selectedIndex > 0) {
            $("#version")[0].selectedIndex = 0;
        }
        $(this).closest("form").submit();
    });

  });

</script>

<form id="filter_form" method="get">
<div class="ui-widget ui-widget-content ui-corner-all">
    <div class="ui-widget-header" style="padding: 0.5em;">Filter</div>

    <div class="filterelem">
        <div class="radio">
            Channel<br />
            <input type="radio" id="chan1" name="chan" value="" <?php echo !$this->chan ? 'checked="checked"' : '' ?>/><label for="chan1">All</label>
            <input type="radio" id="chan2" name="chan" value="Replex" <?php echo $this->chan == "Replex" ? 'checked="checked"' : '' ?>/><label for="chan2">Replex</label>
            <input type="radio" id="chan3" name="chan" value="ReplexAlpha" <?php echo $this->chan == "ReplexAlpha" ? 'checked="checked"' : '' ?>/><label for="chan3">ReplexAlpha</label>
        </div>
    </div>

    <div class="filterelem">
        Version<br/>
        <select id="version" class="ui-widget-content" name="version" style="width: 100px;"> 
            <option value="" <?php echo !$this->version ? 'selected="selected"' : '' ?>>All</option>
<?php
for($i = 0; $i < count($ver); $i++)
{
    $sel = $this->version == $ver[$i] ? ' selected="selected"' : '';
    print '<option value="' . htmlentities($ver[$i]) . '"' . $sel . '>' . htmlentities($ver[$i]). '</option>';
}
?>
        </select>

        <select id="version_min" class="ui-widget-content" name="version_min" style="width: 100px;"> 
            <option value="" <?php echo !$this->version_min ? 'selected="selected"' : '' ?>>Min</option>
<?php
for($i = 0; $i < count($ver); $i++)
{
    $sel = $this->version_min == $ver[$i] ? ' selected="selected"' : '';
    print '<option value="' . htmlentities($ver[$i]) . '"' . $sel . '>' . htmlentities($ver[$i]). '</option>';
}
?>
        </select>

        <select id="version_max" class="ui-widget-content" name="version_max" style="width: 100px;"> 
            <option value="" <?php echo !$this->version_max ? 'selected="selected"' : '' ?>>Max</option>
<?php
for($i = 0; $i < count($ver); $i++)
{
    $sel = $this->version_max == $ver[$i] ? ' selected="selected"' : '';
    print '<option value="' . htmlentities($ver[$i]) . '"' . $sel . '>' . htmlentities($ver[$i]). '</option>';
}
?>
        </select>
    </div>

    <div class="filterelem">
        <div class="radio">
            Operating system<br />
            <input type="radio" id="os_all" name="os" value="" <?php echo !$this->os ? 'checked="checked"' : '' ?>/><label for="os_all">All</label>
            <input type="radio" id="os_windows" name="os" value="Windows NT" <?php echo $this->os == "Windows NT" ? 'checked="checked"' : '' ?>/><label for="os_windows">Windows</label>
            <input type="radio" id="os_linux" name="os" value="Linux" <?php echo $this->os == "Linux" ? 'checked="checked"' : '' ?>/><label for="os_linux">Linux</label>
            <input type="radio" id="os_mac" name="os" value="Mac OS X" <?php echo $this->os == "Mac OS X" ? 'checked="checked"' : '' ?> /><label for="os_mac">Mac</label>
        </div>
    </div>


    <div class="filterelem">
        Grid<br/>
        <select class="ui-widget-content" name="grid" onchange="this.form.submit();" style="width: 200px;"> 
            <option value="" <?php echo !$this->grid ? 'selected="selected"' : '' ?>>All</option>
<?php
for($i = 0; $i < count($grids); $i++)
{
    $sel = $this->grid == $grids[$i] ? ' selected="selected"' : '';
    print '<option value="' . htmlentities($grids[$i]) . '"' . $sel . '>' . htmlentities($grids[$i]). '</option>';
}
?>
        </select>
    </div>

    <div class="filterelem" style="padding-top: 8px;">
        <div style="display: inline-block">
            <br/>
            <select class="ui-widget-content" name="search_type">
                <option value="stack" <?php echo $this->search_type == "stack" ? ' selected="selected"' : "" ?>>Stacktrace</option>
                <option value="gpu" <?php echo $this->search_type == "gpu" ? ' selected="selected"' : "" ?>>GPU</option>
                <option value="os" <?php echo $this->search_type == "os" ? ' selected="selected"' : "" ?>>OS</option>
                <option value="region" <?php echo $this->search_type == "region" ? ' selected="selected"' : "" ?>>Region</option>
            </select>
        </div>
        <div style="display: inline-block">
                    contains<br/>
                    <input class="ui-widget-content" type="text" name="search_for" value="<?php echo htmlentities($this->search_for) ?>" />
                    <input class="toolbarbutton" type="submit" name="do_search" value="Search" />
        </div>
    </div>
</div>

<?php if ($this->region): ?>
<input type="hidden" name="region" value="<?php echo htmlentities($this->region) ?>" />
<?php endif ?>

<?php if ($this->gpu): ?>
<input type="hidden" name="gpu" value="<?php echo htmlentities($this->gpu) ?>" />
<?php endif ?>

<?php if ($this->signature_id): ?>
<input type="hidden" name="signature_id" value="<?php echo htmlentities($this->signature_id) ?>" />
<?php endif ?>

<input type="hidden" id="limit" name="limit" value="<?php echo (int)($this->limit) ?>" />
<input type="hidden" id="offset" name="offset" value="<?php echo (int)($this->offset) ?>" />

</form>
        
<?php
    }
    
    function renderPaginator($total)
    {
        $l = $_SERVER["PHP_SELF"];
        
        ob_start();
?>
<script>
    $(function() {
        
        var $filter = $("#filter_form");
        var $offset = Number($("#offset").val());
        var $limit = Number($("#limit").val());
        var $total = Number(<?php echo $total ?>);
        var $max_offset = (Math.floor($total / $limit)) * $limit;
        //console.log("Offset: " + $offset + "; Max offset: " + $max_offset);
        
        $("#first").button({
            disabled: $offset == 0,
            text: false,
            icons: { primary: "ui-icon-seek-start" },
        })
        .on("click", function() {
            $("#offset").val(0);
            $filter.submit();
        });

        $("#previous").button({
            disabled: $offset == 0,
            text: false,
            icons: { primary: "ui-icon-seek-prev" },
        })
        .on("click", function() {
            $offset -= $limit;
            if ($offset < 0 ) $offset = 0;
            $("#offset").val($offset);
            $filter.submit();
        });

        $("#next").button({
            disabled: $offset >= $max_offset,
            text: false,
            icons: { primary: "ui-icon-seek-next" },
        })
        .on("click", function() {
            $offset += $limit;
            if ($offset > $total ) $offset = $total;
            $("#offset").val($offset);
            $filter.submit();
        });

        $("#last").button({
            disabled: $offset >= $max_offset,
            text: false,
            icons: { primary: "ui-icon-seek-end" },
        })
        .on("click", function() {
            $("#offset").val($max_offset);
            $filter.submit();
        });
        
    });
</script>

<!--div id="paginator" class="ui-widget-header ui-corner-all" style="display: inline-block; padding: 4px"-->
    <a id="first">First</a>
    <a id="previous">Previous</a>
    <div style="display: inline-block; text-align: center; width: 175px;">
        <?php echo $this->offset + 1 ?> - 
        <?php echo $this->offset + $this->limit > $total ? $total : $this->offset + $this->limit  ?> out of
        <?php echo $total ?>
    </div>
    <a id="next">Next</a>
    <a id="last">Last</a>
<!--/div-->

<?php
        return ob_get_clean();
    }
    
}