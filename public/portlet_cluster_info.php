<?php
	require __DIR__ . '/../kafka-php/src/Kafka/Kafka.php';
	header("Cache-Control: max-age=10, no-store"); //must-revalidate

	$connectionString = urldecode($_SERVER['QUERY_STRING']);
	$zk = new ZooKeeper($connectionString);
	
	$brokers = array();
	if ($brokerIds = @$zk->getChildren('/brokers/ids'))
	{
		foreach($brokerIds as $brokerId)
		{
			$brokerHash = @$zk->get("/brokers/ids/{$brokerId}");
			$hostPort = explode(":",$brokerHash);
			$brokers[$brokerId] = "{$hostPort[1]}:{$hostPort[2]}";
			$kafka = new \Kafka\Kafka($hostPort[1], $hostPort[2], 30);
			$consumers[$brokerId] = $kafka->createConsumer(); 
		}
	}
	
	$topics = array();
	if ($topicNames = @$zk->getChildren('/brokers/topics'))
	{
		foreach($topicNames as $topicName)
		{
			foreach($brokers as $brokerId => $brokerName)
			{		
				$brokerPartitionCount = @$zk->get("/brokers/topics/{$topicName}/{$brokerId}");
				for($partition=0; $partition<$brokerPartitionCount; $partition++)
				{
					$topics[$topicName]["{$brokerId}-{$partition}"] = array(
						'earliest' => array_shift($consumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_EARLIEST)),
					    'latest' => array_shift($consumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_LATEST)),
					);
				}
			}
		}
	}
	
	$colors[0] = 'blue';
	$colors[1] = 'green';
	$colors[2] = 'maroon';
	
?>
<body>
	<div id='brokers'>		
		<?php if ($brokers !== null) :?>
		<ul>
		<?php foreach($brokers as $brokerId => $brokerName) : ?>
			<li><?php echo "[{$brokerId}] ";?><small><?php echo $brokerName;?></small></li>		
		<?php endforeach;?>
		</ul>
	</div>
	<div id='topics'>		
		<ul>			
			<?php foreach($topics as $topicName => $partitions) : ?>
			<li>		
				<b><?php echo $topicName;?></b>		
				<?php $p=0;foreach($partitions as $partition => $offsets) : ?>
				<span style="color: <?php echo $colors[$p++];?>;"><?php echo " [<b>{$partition}</b>] ";?>
				<small><?php echo trim($offsets['earliest'],'0') .'-';?></small>
				<small><?php echo trim($offsets['latest'],0);?></small>
				</span>
				<?php endforeach;?>
			</li>
			<?php endforeach;?>
		</ul>		

		<?php endif;?>
	</div>
	
	<pre id ='inspector'><?php
	//print_r($zk->getChildren('/brokers/topics'));
	
	?></pre>
	
</body>