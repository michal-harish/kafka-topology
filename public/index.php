<?php 

require '../gridportal/src/.php';
$gridportal_debug_mode = true;

?><!DOCTYPE html>
<html xmlns:g="portal/portlets.xsd">
<head>
	<!-- standard html head -->
	<title>Kafka PHP Monitor</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<!-- 
	<link rel="stylesheet" type="text/css" href="http://media.gridport.co/aos/main.css" />
	 -->
	<g:portlet id="local" url="/kafka-topology/public/portlet_cluster_info.php?localhost:2181"/>
	<g:portlet id="aos3" url="/kafka-topology/public/portlet_cluster_info.php?aos3.gridport.co:2181"/>
</head>
<body>
	<h2><span>Kafka Topology Scanner</span></h2>
	<table border="1" style="float:left;">
		<tr>
			<th>local kafka brokers / topics</th>
		</tr>
		<tr>
			<td>										
				<g:portlet id="local" fragment="brokers"/>
				<hr/>
				<g:portlet id="local" fragment="topics"/>
			</td>			
		</tr>
	</table>
	<table border="1" style="float:left;">
		<tr>
			<th>aos3 kafka brokers / topics</th>
		</tr>		
		<tr>
			<td>
				<g:portlet id="aos3" fragment="brokers"/>
				<hr/>
				<g:portlet id="aos3" fragment="topics"/>
			</td>
		</tr>
	</table>
</body>
</html>