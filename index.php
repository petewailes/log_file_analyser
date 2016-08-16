<?php
/*
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/ // uncomment this if you want to debug

ini_set('memory_limit', '1024M'); // set based on how much you've got available
date_default_timezone_set('UTC'); // set to the timezone your server uses

function debug ($data, $mode = 0, $title = NULL)
{
	$backtrace = debug_backtrace();

	echo ($title != NULL) ? "<h2>$title</h2>" : '<h2>debug</h2>';

	if (isset($backtrace[1]))
		echo '<p>'.$backtrace[0]['file'].': '.$backtrace[0]['line'].'</p><pre>';
	else
		echo '<p>'.$backtrace[0]['file'].': '.$backtrace[0]['line'].'</p><pre>';

	var_dump($data);
	echo '</pre>';

	exit;
}

class LogParser
{
	private $regex;

	public function setRegex ($regex) { $this->regex = $regex; }
	public function getRegex () { return (string) $this->regex; }

	public function parse ($line) {
		if (!preg_match($this->regex, $line, $matches))
			return FALSE;

		$entry = new stdClass();

		foreach (array_filter(array_keys($matches), 'is_string') as $key) {
			if ('time' === $key && true !== $stamp = strtotime($matches[$key]))
				$entry->stamp = $stamp;

			$entry->{$key} = $matches[$key];
		}

		return $entry;
	}
}

$parser = new LogParser();

$parser->setRegex("/^(?P<ipAddress>\S+) (\S+) (\S+) \[(?P<date>[^:]+):(?P<time>\d+:\d+:\d+) (?P<timezoneOffset>[^\]]+)\] \"(?P<httpMethod>\S+) (?P<urlPath>.*?) (?P<responseType>\S+)\" (?P<responseCode>\S+) (?P<bytesSent>\S+) (?P<referrer>\".*?\") (?P<requestFrom>\".*?\")$/");

$log_store = array();
$log_lines = file('2016-08-15-access.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // testing mode
// $log_lines = file(date('Y-m-d').'-access.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // prod mode

$total_count = 0;
$all_data = array();
$googlebot_crawl = array();

foreach ($log_lines as $line) {
	$entry = $parser->parse($line);

	if (is_object($entry)) {
		$log_store[] = $entry;
		$total_count++;

		$cleaned_path = (strpos($entry->urlPath, "?") !== FALSE) ? substr($entry->urlPath, 0, strpos($entry->urlPath, "?")) : $entry->urlPath;

		$parts = pathinfo($cleaned_path);
		$ext = (isset($parts['extension'])) ? $parts['extension'] : 'html';

		if (isset($all_data['codes'][$entry->responseCode]['requests']))
			$all_data['codes'][$entry->responseCode]['requests']++;
		else
			$all_data['codes'][$entry->responseCode]['requests'] = 1;

		if (isset($all_data['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]))
			$all_data['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]++;
		else
			$all_data['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath] = 1;

		if (isset($all_data['codes'][$entry->responseCode]['exts'][$ext][$entry->urlPath]))
			$all_data['codes'][$entry->responseCode]['exts'][$ext][$entry->urlPath]++;
		else
			$all_data['codes'][$entry->responseCode]['exts'][$ext][$entry->urlPath] = 1;

		if (strpos($entry->requestFrom, 'Googlebot')) {
			if (isset($googlebot_crawl['codes'][$entry->responseCode]['requests']))
				$googlebot_crawl['codes'][$entry->responseCode]['requests']++;
			else
				$googlebot_crawl['codes'][$entry->responseCode]['requests'] = 1;

			if (isset($googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]))
				$googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath]++;
			else
				$googlebot_crawl['codes'][$entry->responseCode]['urlPaths'][$entry->urlPath] = 1;

			if (isset($googlebot_crawl['dates'][$entry->date]))
				$googlebot_crawl['dates'][$entry->date]++;
			else
				$googlebot_crawl['dates'][$entry->date] = 1;
		}
	}
}

foreach ($all_data['codes'] as $code => $code_data) {
	arsort($all_data['codes'][$code]['urlPaths']);

	$all_data['codes'][$code]['percentage'] = $code_data['requests'] / $total_count;
	$all_data['codes'][$code]['uniqueUrls'] = count($code_data['urlPaths']);
}

foreach ($googlebot_crawl['codes'] as $code => $code_data) {
	arsort($googlebot_crawl['codes'][$code]['urlPaths']);
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Log File Analyser</title>
	<link rel="stylesheet" type="text/css" href="minimal_css.min.css">
</head>
<body>
	<div id="wrapper" class="row">
		<h1>Log File Analyser</h1>
		<script src="https://code.highcharts.com/highcharts.js"></script>
		<h2>Resources Crawled per Day</h2>
		<div id="chart"></div>
		<script>
		window["chart"] = new Highcharts.Chart({"credits":{"enabled":false},"chart":{"backgroundColor":"#fefefe","renderTo":"chart","type":"spline","spacingTop":20,"spacingBottom":20},"title":{"text":null},"colors":["#3fa9f5","#476974","#3ca1c1","#4ccbf4","#96dff6","#c9e8f6"],"legend":{"enabled":true,"margin":30},"plotOptions":{"series":{"animation":false}},"tooltip":{"backgroundColor":"#3fa9f5","borderColor":"#3fa9f5","borderRadius":0,"shadow":false,"style":{"color":"#ffffff","padding":"15px","font-size":"12px"}},"xAxis":{"tickWidth":0,"labels":{"y":20},"categories":["<?php echo implode('","', array_keys($googlebot_crawl['dates'])) ?>"]},"yAxis":{"tickWidth":0,"labels":{"y":0},"title":{"text":"Values"}},"series":[{"name":"Crawled resources per day","data":[<?php echo implode(',', $googlebot_crawl['dates']) ?>]}]});
		</script>
		<p><small>Created with the <a href="https://builtvisible.com/highcharts-generator/">Highcharts Generator tool</a></small></p>
		<?php if (isset($googlebot_crawl['codes'][301])): ?>
		<h2>Googlebot Crawled 301 Code URIs (<small><?php echo $googlebot_crawl['codes'][301]['requests'] ?></small>)</h2>
		<table>
			<thead>
				<tr>
					<th>Path</th>
					<th>Count</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($googlebot_crawl['codes'][301]['urlPaths'] as $path => $count): ?>
				<tr>
					<td><?php echo $path ?></td>
					<td><?php echo $count ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		endif;
		if (isset($googlebot_crawl['codes'][302])):
		?>
		<h2>Googlebot Crawled 302 Code URIs (<small><?php echo $googlebot_crawl['codes'][302]['requests'] ?></small>)</h2>
		<table>
			<thead>
				<tr>
					<th>Path</th>
					<th>Count</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($googlebot_crawl['codes'][302]['urlPaths'] as $path => $count): ?>
				<tr>
					<td><?php echo $path ?></td>
					<td><?php echo $count ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		endif;
		if (isset($googlebot_crawl['codes'][404])):
		?>
		<h2>Googlebot Crawled 404 Code URIs (<small><?php echo $googlebot_crawl['codes'][404]['requests'] ?></small>)</h2>
		<table>
			<thead>
				<tr>
					<th>Path</th>
					<th>Count</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($googlebot_crawl['codes'][404]['urlPaths'] as $path => $count): ?>
				<tr>
					<td><?php echo $path ?></td>
					<td><?php echo $count ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</body>
</html>