<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');

function data_storing($attemptid,$timenow,$startTime)
{
    global $DB;

	$dataquery = $DB->get_field_sql('SELECT qat.id FROM {quiz_attempts} qa inner join {question_attempts} qat on qa.uniqueid = qat.questionusageid WHERE qa.id = :attemptid AND qat.timemodified = :timemodified', ['attemptid' => $attemptid, 'timemodified' => $timenow]);


	$questionattemptid = (int) $dataquery;
	$startTime = date("Y-m-d H:i:s", $startTime);
	$endTime = date("Y-m-d H:i:s", $timenow);

	$table = "quiz_emond_timer";
	$dataobject = (object) [
		"questionattemptid" => $questionattemptid,
		"TimeStart" => $startTime,
		"TimeEnd" => $endTime
	];


	$dataResponse = $DB->get_field_sql('SELECT responsesummary FROM {question_attempts} WHERE id = :id', ['id' => $questionattemptid]);

	$startYear = substr($startTime, 0, 4);
	$endYear = substr($endTime, 0, 4);


	/*$myfile = fopen("newfile.txt", "w") or die("Unable to open file");
	$txt = $attemptid . "<- attemptid /";
	fwrite($myfile, $txt);
	$txt = $timenow . "<- timenow /";
	fwrite($myfile, $txt);
	$txt = $questionattemptid . "<- questionattemptid /";
	fwrite($myfile, $txt);
	fclose($myfile); */

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Makes sure the id is not 0, the years match, and that the user responded to the question and submitted it, before entering to the Time Table
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	if($questionattemptid != 0 && $startYear == $endYear ){
		$DB->insert_record($table, $dataobject);
	}

}


 function testing_query($attemptid){
    global $DB;
	$testQuery = $DB->get_records_sql('
	SELECT
		t.rawname AS tag,
		quiza.uniqueid,
		quiz.name AS quiz,
		quiza.attempt,
		u.firstname + u.lastname AS student,
		u.email,
		Case when SUM(qas.fraction) is null then 0 else SUM(qas.fraction) end AS correct,
		SUM(qa.maxmark) AS maximum,
		case when (SUM(qas.fraction)/SUM(qa.maxmark)*100) is null then 0 else SUM(qas.fraction)/SUM(qa.maxmark)*100 End AS score,
		1 as Email_Sent
	FROM mdl_quiz_attempts quiza
	JOIN mdl_user u ON quiza.userid = u.id
	JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid
	JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id
	JOIN mdl_quiz quiz ON quiz.id = quiza.quiz
	JOIN mdl_tag_instance ti ON qa.questionid = ti.itemid
	JOIN mdl_tag t ON t.id = ti.tagid
	JOIN (  select MAX(qas.fraction) AS fraction, qas.questionattemptid
			FROM mdl_quiz_attempts quiza
			JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid
			JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id
			join mdl_question_attempt_steps qas on qas.questionattemptid = qa.id
			where quiza.id = :attemptid
			GROUP BY qas.questionattemptid) qas ON qas.questionattemptid = qa.id
	GROUP BY 
		t.rawname,
		quiza.uniqueid,
		quiz.name,
		quiza.attempt,
		u.email,
		u.firstname,
		u.lastname
	ORDER BY quiza.uniqueid, quiz.name, t.rawname, score',['attemptid' => $attemptid]);
    return $testQuery;
}

function question_tagquery($attemptid){
    global $DB;
	$tagTimeQuery = $DB->get_records_sql('
	Select t.name, sum(x.days) as [sum(x.days)], sum(x.hours) as [sum(x.hours)], sum(x.minutes) as [sum(x.minutes)], sum(x.seconds) as [sum(x.seconds)]
	from
				(SELECT
					questionattemptid,
					(DATEDIFF(s, et.TimeStart, et.TimeEnd) / 86400 ) as [Days],
					( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) / 3600 ) as [Hours],
					( ( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) % 3600 ) / 60 ) as [Minutes],
					( ( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) % 3600 ) % 60 ) as [Seconds]
				 from mdl_quiz_emond_timer et
				inner join mdl_question_attempts qa on et.questionattemptid = qa.id
				 inner join mdl_quiz_attempts quiza on qa.questionusageid = quiza.uniqueid
				 where questionattemptid <> 0
				and quiza.id = :attemptid
				) x
	inner join mdl_question_attempts qa on qa.id = x.questionattemptid
	inner join mdl_tag_instance ti ON qa.questionid = ti.itemid
	inner join mdl_tag t ON t.id = ti.tagid
	group by t.name',['attemptid' => $attemptid]);

    return $tagTimeQuery;
}

function question_timequery($attemptid) {
    global $DB;
	$questionTimeQuery = $DB->get_records_sql('
	Select x.questionattemptid, t.name, sum(x.days) as [sum(x.days)], sum(x.hours) as [sum(x.hours)], sum(x.minutes) as [sum(x.minutes)], sum(x.seconds) as [sum(x.seconds)]
	from
				(SELECT  
					questionattemptid,
					(DATEDIFF(s, et.TimeStart, et.TimeEnd) / 86400 ) as [Days],
					( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) / 3600 ) as [Hours],
					( ( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) % 3600 ) / 60 ) as [Minutes],
					( ( ( DATEDIFF(s, et.TimeStart, et.TimeEnd) % 86400 ) % 3600 ) % 60 ) as [Seconds]
					from mdl_quiz_emond_timer et
					inner join mdl_question_attempts qa on et.questionattemptid = qa.id
					inner join mdl_quiz_attempts quiza on qa.questionusageid = quiza.uniqueid
					where questionattemptid <> 0
					and quiza.id = :attemptid
				) x
	inner join mdl_question_attempts qa on qa.id = x.questionattemptid
	inner join mdl_tag_instance ti ON qa.questionid = ti.itemid
	inner join mdl_tag t ON t.id = ti.tagid
	group by x.questionattemptid, t.name',['attemptid' => $attemptid]);


	$questionTimeConverted = json_decode(json_encode($questionTimeQuery), TRUE);  // converting from stdClass to Associative Array
	$questionArray = array();  //create empty array

	foreach($questionTimeConverted as $key => $value){  //create a new array with the previous data with int keys
		array_push($questionArray, $value);
	}


	$dataquery = $DB->get_records_sql('SELECT qa.id FROM mdl_question_attempts qa inner join mdl_quiz_attempts quiza on qa.questionusageid = quiza.uniqueid WHERE quiza.id = :attemptid', ['attemptid' => $attemptid]);

	$questionIdArray = array();

	$dataqueryConverted = json_decode(json_encode($dataquery), TRUE); //converting from json object to associative array

	foreach ($dataqueryConverted as $key => $value){
		array_push($questionIdArray, $value);
	}

	$finalQuestionArray = array();

	foreach ($questionIdArray as $key => $value){
		$id = $value["id"];  //its in string
		
		$placeholder = array();


		foreach($questionArray as $single){
			$questionattemptid = $single["questionattemptid"];
			if($questionattemptid == $id){
				array_push($placeholder, $single);
			}
		}
		
		if(isset($placeholder[0])){
			array_push($finalQuestionArray, $placeholder[0]);
		} else {
			$x = array(
				"questionattemptid" => $id,
				"name" => "",
				"sum(x.days)" => "0",
				"sum(x.hours)" => "0",
				"sum(x.minutes)" => "0",
				"sum(x.seconds)" => "0"
			);
			array_push($finalQuestionArray, $x);
		}
	}
	return $finalQuestionArray;
}

