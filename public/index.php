<?php
require '../gridportal/src/.php';
$uriBase = array_shift(preg_split('/\/*\?/', $_SERVER['REQUEST_URI']));
if (!$_SERVER['QUERY_STRING']) {
	exit(header("Location: ?Local=localhost:2181"));
}
?><!DOCTYPE html>
<html xmlns:g="portal/portlets.xsd">
<head>
	<!-- standard html head -->
	<title>Kafka Scanner</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<link rel="stylesheet" type="text/css" href="<?php echo $uriBase;?>/main.css" />
	<g:portlet id="test" url="<?php echo $uriBase;?>/portlet_test.phtml"/>
	<?php foreach($_GET as $key => $zkConnection) : ?>
		<g:portlet 
			id="<?php echo $zkConnection?>" 
			url="<?php echo $uriBase;?>/portlet_cluster.phtml?<?php echo $zkConnection?>"
		/>
	<?php endforeach ?>
</head>
<body>
	<h2><span>Kafka Topology Scanner</span></h2>
	
	<g:portlet id="test" class="ajax" fragment="time"/>
	<g:portlet id="test" fragment="date"/>
	 
	<hr/>	
	<?php foreach($_GET as $key => $zkConnection) : ?>
	   <div class="clusterInstance">
	   <h3>'<?php echo $key?>' kafka cluster</h3>

	   <g:portlet class="ajax" id="<?php echo $zkConnection?>" fragment="topicsTable"/>
	   <g:portlet class="ajax" id="<?php echo $zkConnection?>" fragment="consumerList"/>
       </div>
	<?php endforeach;?>
</body>
</html>
