<link rel="stylesheet" type="text/css" href="./plugins/tableSorter/inc/blue/style.css">
<script src="./plugins/tableSorter/inc/jquery.tablesorter.min.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function() 
	{
		$(".tablesorter").tablesorter();
		$(".unsortable").removeClass("header").each(function (index) {
			this.sortDisabled = true;
		});
	}
); 
</script>