<?php
	require __DIR__ . '/../kafka-php/src/Kafka/Kafka.php';
	
	$connectionString = urldecode($_SERVER['QUERY_STRING']);
	$zk = new ZooKeeper($connectionString);
	
	if ($consumerIds = @$zk->getChildren('/consumers')) {
		$consumers =  array_fill_keys($consumerIds, array());
	} else $consumers = array();
	
	if (isset($_SERVER['HTTP_PORTLET_AJAX'])) {
		class watcher {
			private $changedPath = null;
			public function __construct($zk, array $paths, $min_sleep_sec = 1) {			
				foreach($paths as $path) $zk->getChildren($path, array($this, 'watch'));
				while ($this->changedPath === null) sleep($min_sleep_sec);
			}
			public function watch($event, $key, $path) {
				$this->changedPath = $path;
			}
		}
		$watchPaths = array('/brokers/ids', '/consumers');
		foreach(array_keys($consumers) as $consumer) {
			$watchPaths[] = "/consumers/{$consumer}/ids";
		}
		new watcher($zk, $watchPaths);
	}
	 
	header("Cache-Control: max-age=0, no-store");
	
	$brokers = array();
	if ($brokerIds = @$zk->getChildren('/brokers/ids'))
	{		
		foreach($brokerIds as $brokerId)
		{
			$brokerHash = @$zk->get("/brokers/ids/{$brokerId}");
			$hostPort = explode(":",$brokerHash);
			$brokers[$brokerId] = "{$hostPort[1]}:{$hostPort[2]}";
			$kafka = new \Kafka\Kafka($hostPort[1], $hostPort[2], 30);
			$kafkaConsumers[$brokerId] = $kafka->createConsumer(); 
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
						'earliest' => array_shift($kafkaConsumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_EARLIEST)),
					    'latest' => array_shift($kafkaConsumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_LATEST)),
					);
					$topics[$topicName][$partitionId] = $partitionStatus;
					$sections[$topicName][$brokerId][$partition] = $partitionStatus;
				}
			}
		}
	}
	
	foreach(array_keys($consumers) as $consumerId)
	{
		$consumerInfo = array();
		foreach(@$zk->getChildren("/consumers/{$consumerId}/offsets") as $topicName) {
			$consumerTopic = array();		
			foreach(@$zk->getChildren("/consumers/{$consumerId}/offsets/{$topicName}") as $partitionId) {				
				$consumerPartitionStatus['watermark'] = @$zk->get("/consumers/{$consumerId}/offsets/{$topicName}/$partitionId");
				$consumerTopic['partition'][$partitionId] = $consumerPartitionStatus;
			}
			$consumerTopicOwners = @$zk->getChildren("/consumers/{$consumerId}/owners/{$topicName}");
			if ($consumerTopicOwners) {
				foreach($consumerTopicOwners as $consumerProcess) {
					$consumerTopic['process'][$consumerProcess] = array();
				}
			}
			$consumerInfo['topics'][$topicName] = $consumerTopic;
		}
		$consumers[$consumerId] = $consumerInfo;			
	}	
	
	unset($zk);
	
?>
<body>
	<div id='topicsTable'>	
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

	<ul id='consumerList'>	
		<?php if ($consumers !== null) :?>
			<?php foreach($consumers as $consumerId => $info) : ?>
				<li>
					'<b><?php echo $consumerId;?></b>' consumes:
					<?php foreach($info['topics'] as $topicName => $consumerTopic): ?>
						<b><?php echo $topicName;?></b> {						
						<?php foreach($consumerTopic['partition'] as $partitionId => $consumerPartitionStatus): ?>
							[<?php echo $partitionId;?>]:<?php echo $consumerPartitionStatus['watermark'] . '/'. trim($topics[$topicName][$partitionId]['latest'],0);?>
						<?php endforeach;?> 
						} using <b><?php echo count($consumerTopic['process'])?></b> active processes;						
					<?php endforeach;?> 
				</li>		
			<?php endforeach;?>
		<?php endif;?>
	</ul>
	
</body>