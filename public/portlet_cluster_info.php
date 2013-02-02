<?php
	require __DIR__ . '/../kafka-php/src/Kafka/Kafka.php';
	
	$connectionString = urldecode($_SERVER['QUERY_STRING']);
	$zk = new ZooKeeper($connectionString);
	
	
	if (isset($_SERVER['HTTP_PORTLET_AJAX'])) {
		class watcher {
			private $changedPath = null;
			public function __construct($zk, $path, $min_sleep_sec = 1) {				
				$zk->getChildren($path, array($this,'watch'));
				while ($this->changedPath != $path) sleep($min_sleep_sec);
			}
			public function watch($event, $key, $path) {
				$this->changedPath = $path;
			}
		}
		switch($_SERVER['HTTP_PORTLET_FRAGMENTS']) {
			case 'brokers': new watcher($zk, '/brokers/ids'); break;
			case 'consumers': new watcher($zk, '/consumers'); break;
		}			
	}
	 
	header("Cache-Control: max-age=0, no-store");
	
	$brokers = array();
	if ($brokerIds = $zk->getChildren('/brokers/ids'))
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
					$partitionId = "{$brokerId}-{$partition}";
					$partitionStatus = array(
						'id' => $partitionId,
						'earliest' => array_shift($consumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_EARLIEST)),
					    'latest' => array_shift($consumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_LATEST)),
					);
					$topics[$topicName][$partitionId] = $partitionStatus;
					$sections[$topicName][$brokerId][$partition] = $partitionStatus;
				}
			}
		}
	}
	
	unset($zk);
	
?>
<body>
	<div id='brokers'>	
		<?php if ($brokers !== null) :?>
		<table>
			<thead>
				<tr>
					<th><?php echo $connectionString;?></th>
				<?php foreach($topics as $topicName => $partitions) : ?>
					<th><?php echo "$topicName(".count($partitions).")"; ?></th>
				<?php endforeach;?>
				</tr>
			</thead>
			<tbody>
				<?php foreach($brokers as $brokerId => $brokerName) : ?>
					<tr>
						<th><?php echo "[{$brokerId}] ";?><small><?php echo $brokerName;?></small></th>
						<?php foreach(array_keys($topics) as $topicName): ?>
						<td>
							<?php $p=0;foreach($sections[$topicName][$brokerId] as $partition => $status) : ?>
							<span class="alterColor<?php echo ++$p;?>"><?php echo " [<b>{$status['id']}</b>] ";?>
							<small><?php echo trim($status['earliest'],'0') .'-';?></small>
							<small><?php echo trim($status['latest'],0);?></small>
							</span><br/>
							<?php endforeach;?>
						</td>
						<?php endforeach; ?>
					</tr>		
				<?php endforeach;?>
			</tbody>
		</table>
		<?php endif;?>
	</div>
	
	<ul id='consumers'>
		<li>..</li>
	</ul>
	
	<pre id ='inspector'><?php
	//print_r($zk->getChildren('/brokers/topics'));
	
	?></pre>
	
</body>