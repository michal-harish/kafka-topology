<?php
$uriBase = array_shift(explode("?", $_SERVER['REQUEST_URI']));
require '../gridportal/src/.php';
?><!DOCTYPE html>
<html xmlns:g="portal/portlets.xsd">
<head>
	<!-- standard html head -->
	<title>Kafka PHP Monitor</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<link rel="stylesheet" type="text/css" href="<?php echo $uriBase;?>/main.css" />
	<?php foreach($_GET as $key => $zkConnection) : ?>
		<g:portlet 
			id="<?php echo $zkConnection?>" 
			url="<?php echo $uriBase;?>/portlet_cluster.php?<?php echo $zkConnection?>"
		/>
	<?php endforeach ?>
</head>
<body>
	<h2><span>Kafka Topology Scanner</span></h2>
	<hr/>	
	<?php foreach($_GET as $key => $zkConnection) : ?>	
	<h3>'<?php echo $key?>' kafka cluster</h3>
	<g:portlet class="ajax" id="<?php echo $zkConnection?>" fragment="topicsTable"/>
	<g:portlet class="ajax" id="<?php echo $zkConnection?>" fragment="consumerList"/>
	<?php endforeach;?>		
</body>
</html>