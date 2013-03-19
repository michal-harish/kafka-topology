<?php
	require __DIR__ . '/../kafka-php/src/Kafka/Kafka.php';

	header("Cache-Control: max-age=0, no-store");

	ini_set("zookeeper.recv_timeout", "30000");
	$connectionString = urldecode($_SERVER['QUERY_STRING']);
	$zk = new ZooKeeper($connectionString);

	$consumers =   @$zk->exists('/consumers') ? array_fill_keys(@$zk->getChildren('/consumers'), array()) : array();
	class watcher {
		private $changedPath = null;
		public function __construct($zk, array $paths, $min_sleep_sec = 1) {
		    foreach($paths as $path) {
		        if (@$zk->exists($path)) @$zk->getChildren($path, array($this, 'watch'));
		    }
		    $t = microtime(true);
		    while (microtime(true) - $t < 60 * 5 && $this->changedPath === null) sleep($min_sleep_sec);
		}
		public function watch($event, $key, $path) {
			$this->changedPath = $path;
		}
	}
	if (isset($_SERVER['HTTP_PORTLET_AJAX'])) 
	{
		$watchPaths = array('/brokers/ids', '/consumers');
		foreach(array_keys($consumers) as $consumer) {
			$watchPaths[] = "/consumers/{$consumer}/ids";
		}
		new watcher($zk, $watchPaths);
		sleep(3);
	}

	$brokers = array();
	if ($brokerIds = $zk->getChildren('/brokers/ids'))
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

try {
	if ($topicNames = @$zk->getChildren('/brokers/topics'))
	{
		sort($topicNames);
		foreach($topicNames as $topicName)
		{
			foreach($brokers as $brokerId => $brokerName)
			{
				$brokerPartitionCount = @$zk->get("/brokers/topics/{$topicName}/{$brokerId}");
				for($partition=0; $partition<$brokerPartitionCount; $partition++)
				{
					try {
						$partitionId = "{$brokerId}-{$partition}";
						$smallest = array_shift($kafkaConsumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_EARLIEST));
						$largest = array_shift($kafkaConsumers[$brokerId]->offsets($topicName,$partition, \Kafka\Kafka::OFFSETS_LATEST));
						$partitionStatus = array(
							'id' => $partitionId,
							'smallest' => $smallest->__toString(),
						    'largest' => $largest->__toString(),
						);
						$topics[$topicName][$partitionId] = $partitionStatus;
						$sections[$topicName][$brokerId][$partition] = $partitionStatus;
					} catch (\Kafka\Exception $e) {
						continue;
					}
				}
			}
		}
	}
} catch (Exception $e) {
    echo get_class($e) . $e->getMessage();
}
    $hasAbandonedConsumers = false;
    $hasActiveConsumers = false;
    $hasInactiveConsumers = false;
	foreach(array_keys($consumers) as $consumerId)
	{
		$consumerInfo = array(
		    'id' => $consumerId,
		    'active' => false,
		    'abandoned' => true,
		    'process' => array(),
		);
		if ($consumerIds = @$zk->getChildren("/consumers/{$consumerId}/ids")) {
    		foreach($consumerIds as $process) {
    		    $consumerInfo['process'][$process] = array();
    		}
		}

		foreach(@$zk->getChildren("/consumers/{$consumerId}/offsets") as $topicName) {
			if (!isset($topics[$topicName])) continue;
			$consumerTopic = array(
			    'active' => false,
			    'abandoned' => true,
			    'lagging' => false,
			    'process' => array(),
			    'partition' => array(),
			);
			foreach(@$zk->getChildren("/consumers/{$consumerId}/offsets/{$topicName}") as $partitionId) {
				$consumerPartitionStatus['watermark'] = @$zk->get("/consumers/{$consumerId}/offsets/{$topicName}/$partitionId");
				$smallest = $topics[$topicName][$partitionId]['smallest'];
				$largest = $topics[$topicName][$partitionId]['largest'];
				if (intval($largest) - intval($smallest) == 0) {
				    $consumerPartitionStatus['progress'] = $consumerPartitionStatus['watermark'] == $largest ? 100 : false;
				} elseif ($consumerPartitionStatus['watermark'] < $smallest) {
				    $consumerPartitionStatus['progress'] = false;
				} elseif (is_numeric($smallest) && is_numeric($largest)) {
    				$consumerPartitionStatus['progress'] = round(
    				    100 * (intval($consumerPartitionStatus['watermark']) - intval($smallest))
    				    / (intval($largest) - intval($smallest))
    				    ,2
				    );
    				$consumerTopic['lagging'] = $consumerTopic['lagging'] || ($consumerPartitionStatus['progress'] < 90);
				}
				$consumerTopic['partition'][$partitionId] = $consumerPartitionStatus;
				$consumerTopic['abandoned'] = $consumerTopic['abandoned'] && $consumerPartitionStatus['progress'] === false;
			}

			try {
			  $consumerTopicOwners = @$zk->getChildren("/consumers/{$consumerId}/owners/{$topicName}");
			  if ($consumerTopicOwners) {
				foreach($consumerTopicOwners as $consumerProcess) {
					$consumerTopic['process'][$consumerProcess] = array();
				}
		      }
			} catch (Exception $e) { echo $e->getMessage(); }
			$consumerTopic['active'] = isset($consumerTopic['process']) && count($consumerTopic['process']) > 0;
			$consumerInfo['topics'][$topicName] = $consumerTopic;
			$consumerInfo['abandoned'] &= $consumerTopic['abandoned'] && !$consumerTopic['active'];
			$hasAbandonedConsumers |= $consumerInfo['abandoned']; 
			$hasInactiveConsumers |= !$consumerTopic['active'] && !$consumerInfo['abandoned'];
		}
		$consumerInfo['active'] = count($consumerInfo['process']) > 0;
		$hasActiveConsumers |= $consumerInfo['active'];
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
							<?php $p=0;if (isset($sections[$topicName][$brokerId])) 
							foreach($sections[$topicName][$brokerId] as $partition => $status) : ?>
							<small class="alterColor<?php echo ++$p;?>"><?php 
								$id = str_replace("-", "&nbsp;-&nbsp;", $status['id']);
								echo " [&nbsp;<b>{$id}</b>&nbsp;]&nbsp;"
									.trim($status['smallest'],'0') .'&nbsp;-&nbsp;'
									.trim($status['largest'],0);?>
							</small><br/>
							<?php endforeach;?>
						</td>
						<?php endforeach; ?>
					</tr>		
				<?php endforeach;?>
			</tbody>
		</table>
		<?php endif;?>
	</div>

	<table id='consumerList'>	
	    <tr>
        <?php if ($consumers !== null) :?>
	    <td>
	       <?php if ($hasActiveConsumers) : ?>
	       <h3 class="active" >Active consumer groups</h3>
	       <ul>
			<?php foreach($consumers as $consumerId => $consumerInfo) if ($consumerInfo['active']) : ?>
				<li class="active">
				    <?php renderConsumer($consumerId, $consumerInfo); ?>
				</li>
			<?php endif;?>
		   </ul>
		   <?php endif; ?>

           <?php if ($hasInactiveConsumers) : ?>
		   <h3 class="inactive" >Inactive consumer groups</h3>
           <ul>
            <?php foreach($consumers as $consumerId => $consumerInfo) if (!$consumerInfo['active'] && !$consumerInfo['abandoned']) : ?>
                <li class="inactive">
                    <?php renderConsumer($consumerId, $consumerInfo); ?>
                </li>
            <?php endif;?>
            </ul>
            <?php endif; ?>

        </td>
        <?php if ($hasAbandonedConsumers) : ?>
        <td>
            <h3 class="abandoned">Abandoned consumer groups</h3>
            <ul>
	        <?php foreach($consumers as $consumerId => $consumerInfo) if ($consumerInfo['abandoned']) : ?>
	        <li class="abandoned">
	        	'<b><?php echo $consumerId;?></b>' had consumed:
	        	<?php if (isset($consumerInfo['topics'])) foreach($consumerInfo['topics'] as $topicName => $consumerTopic): ?>
	        	  <small><?php echo $topicName;?></small>, 
	        	<?php endforeach; ?>
	        </li>
	        <?php endif;?>
	        </ul>
	    </td>
	    <?php endif; ?>
	    <?php endif;?>
	    </tr>
	</table>
</body>

<?php function renderConsumer($consumerId, array $consumerInfo) { ?>
    '<b><?php echo $consumerInfo['id'];?></b>' : 
    <b><?php echo count($consumerInfo['process'])?></b> active processes consuming:
    <ul>
    <?php foreach($consumerInfo['topics'] as $topicName => $consumerTopic): ?>
        <li class="<?php echo  $consumerTopic['lagging'] ? "lagging" : ($consumerTopic['active'] ? "active" :  "inactive");?>">
        <small>
        <b><?php echo $topicName;?></b> {
        <?php foreach($consumerTopic['partition'] as $partitionId => $consumerPartitionStatus): ?>
            <span title="(<?php echo $consumerPartitionStatus['watermark'];?>)">
                [<?php echo $partitionId;?>]<?php echo $consumerPartitionStatus['progress'] ;?>%
            </span> 
        <?php endforeach;?> 
        } / <b><?php echo count($consumerTopic['process'])?></b> active streams
        </small>
        </li>
    <?php endforeach;?>
    </ul>
<?php } ?>