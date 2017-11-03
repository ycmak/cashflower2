<?php

	/*
		CashFlower r2
		=============
		TODO:
		* Different thresholds for balance -- drop below certain amount, change colour
		* Graph
		* Summary -- most negative point
		* Generate PDF c/f statement
		* Allow "/" -- use " / " as separator
	*/

	include('fn.moneyformat.inc.php');

	$items = array();
	function addItem($date,$item,$amt,$special=''){
		global $items;
		$items[] = array(
			"date" => $date,
			"item" => $item,
			"amt" => $amt,
			"special" => $special
		);
	}

	setlocale(LC_MONETARY, 'en_US');
	function displayAmt($amt){
		return money_format('%.2n',$amt/100);
	}
	
	function gDate($a){
		$b = substr($a,6,2);
		settype($b,'int');
		return $b;
	}
	
	function gMonth($a){
		$b = substr($a,4,2);
		settype($b,'int');
		return $b;
	}
	
	function gYear($a){
		$b = substr($a,0,4);
		settype($b,'int');
		return $b;	
	}
	
	function pad2($a){
		# assumes $a is integer
		if($a<10) return '0'.$a;
		else return $a;
	}
	
	function parseAmt($amt){
		$amt = str_replace('$','',$amt);
		settype($amt,'float');
		$amt *= 100;
		settype($amt,'int');
		return $amt;
	}
	
	if(isset($_POST['mode']) && $_POST['mode']=='post'){
		# Some variables
		$t_start = 0;
		$t_end = 0;
		$run_bal = 0;
		# Parse input
		$input = urldecode($_POST['accounts']);
		$lines = explode("\n",$input);
		for($i=0;$i<count($lines);$i++){
			$lines[$i] = trim($lines[$i]);
			if(substr($lines[$i],0,1)=='#' || empty($lines[$i])) continue;
			$part = explode("/",$lines[$i]);
			for($j=0;$j<count($part);$j++) $part[$j] = trim($part[$j]);
			switch($part[0]){
				case '[start]':
					$t_start = $part[1];
					settype($t_start,'int');
					break;
				case '[end]':
					$t_end = $part[1];
					settype($t_end,'int');
					break;
				case '[initial]':
					$amt = parseAmt($part[1]);
					addItem($t_start,$part[2],$amt);
					break;
				case '[repeat-monthly]':
					$amt = parseAmt($part[2]);
					settype($part[1],'int');
					$now = $t_start;
					$cnt = 0;
					while($now<=$t_end && $cnt<20){
						if(gDate($now)<=$part[1]){
							addItem((gYear($now).pad2(gMonth($now)).pad2($part[1])),$part[3],$amt,'recur');
						}
						# Advancing
						if(gMonth($now)<12){
							$month = gMonth($now)+1;
							$now = gYear($now).pad2($month).'01';
						}
						else $now = (gYear($now)+1).'01'.'01';
						settype($now,'int');
						$cnt++;
					}
					break;
				case '[repeat-till]':
					$amt = parseAmt($part[3]);
					settype($part[1],'int');
					$now = $t_start;
					$repeat_till = $part[2];
					settype($repeat_end,'int');
					$cnt = 0;
					while($now<=$t_end && $now<=$repeat_till && $cnt<20){
						if(gDate($now)<=$part[1]){
							addItem((gYear($now).pad2(gMonth($now)).pad2($part[1])),$part[4],$amt,'instalment');
						}
						# Advancing
						if(gMonth($now)<12){
							$month = gMonth($now)+1;
							$now = gYear($now).pad2($month).'01';
						}
						else $now = (gYear($now)+1).'01'.'01';
						settype($now,'int');
						$cnt++;
					}					
					break;
				default:
					addItem($part[0],$part[2],parseAmt($part[1]));
					break;
			}
		}
		$tmp = array();
		foreach($items as &$ma) $tmp[] = &$ma['date'];
		array_multisort($tmp,$items);
		$now_month = 0;
		$months = array('','January','February','March','April','May','June','July','August','September','October','November','December');
		$str = '<table border="0" cellspacing="0"><tr><th class="la">Date</th><th class="la">Item</th><th class="ra">A/R</th><th class="ra">A/P</th><th class="ra">Balance</th></tr>';
		foreach($items as &$ma){
			if(gMonth($ma['date'])!=$now_month){
				$now_month = gMonth($ma['date']);
				$str .= '<tr><td colspan="5" class="new_month">'.$months[$now_month].' '.gYear($ma['date']).'</td></tr>';
			}
			$str .= '<tr><td>'.$ma['date'].'</td>';
			if($ma['special']=='recur') $str .= '<td style="color:grey;">'.$ma['item'].'</td>';
			elseif($ma['special']=='instalment') $str .= '<td style="color:navy;">'.$ma['item'].'</td>';
			else $str .= '<td style="font-weight:bold;">'.$ma['item'].'</td>';
			if($ma['amt']>0) $str .= '<td class="td_ar">'.displayAmt($ma['amt']).'</td><td></td>';
			else $str .= '<td></td><td class="td_ap">'.displayAmt($ma['amt']).'</td>';
			$run_bal += $ma['amt'];
			$negative = ($run_bal<0) ? ' class="in_the_red"' : ''; 
			$str .= '<td style="text-align:right;"'.$negative.'>'.displayAmt($run_bal).'</td></tr>';
		}
		$str .= '</table>';
		print($str);
		die();
	}
?>
<html>

<head>
	<title>CashFlower r1</title>
	<link rel='stylesheet' href='main.css' type='text/css'>
</head>

<script language='javascript'>

	q = function(a){
		return document.getElementById(a);
	}
	
	qv = function(a){
		return q(a).value;
	}

	send = function(){
		str = "mode=post";
		str += "&accounts="+encodeURIComponent(qv("ta_accounts"));
		hello = new XMLHttpRequest();
		hello.open("POST", "cashflower.php", true);
		hello.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		hello.send(str);
		hello.onreadystatechange = function(){
			if(hello.readyState==4){
				q("results").innerHTML = hello.responseText;
			}
		}
		return false;
	}

</script>

<body>

	<div id='p_left'>
		<textarea id='ta_accounts'></textarea>
	</div>
	<div id='p_right'>
		<h1>CashFlower r1</h1>
		<p>
			<input type='button' value='Update' onclick='send();'>
		</p>
		<div id='results'></div>
	</div>

</body>

</html>