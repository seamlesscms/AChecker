<?php
/************************************************************************/
/* AChecker                                                             */
/************************************************************************/
/* Copyright (c) 2008 by Greg Gay, Cindy Li                             */
/* Adaptive Technology Resource Centre / University of Toronto          */
/*                                                                      */
/* This program is free software. You can redistribute it and/or        */
/* modify it under the terms of the GNU General Public License          */
/* as published by the Free Software Foundation.                        */
/************************************************************************/

/*
 * This file defines all the html templates used to generate web service html output
 */
if (!defined("AC_INCLUDE_PATH")) die("Error: AC_INCLUDE_PATH is not defined.");

include_once(AC_INCLUDE_PATH.'classes/HTMLRpt.class.php');
include_once(AC_INCLUDE_PATH.'classes/DAO/GuidelinesDAO.class.php');
include_once(AC_INCLUDE_PATH.'classes/DAO/UserLinksDAO.class.php');

class HTMLWebServiceOutput {

	// all private
	var $html_main =
'<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE style PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<style type="text/css">
ul {font-family: Arial; margin-bottom: 0px; margin-top: 0px; margin-right: 0px;}
li.msg_err, li.msg_info { font-family: Arial; margin-bottom: 20px;list-style: none;}
span.msg{font-family: Arial; line-height: 150%;}
code.input { margin-bottom: 2ex; background-color: #F8F8F8; line-height: 130%;}
span.err_type{ padding: .1em .5em; font-size: smaller;}
</style>
<input value="{SESSIONID}" type="hidden" name="sessionid" />
<p>
<strong>Result: </strong>
{SUMMARY}
<strong><br />
<strong>Guides: </strong>
{GUIDELINE}
</p>
{DETAIL}
{BUTTON_MAKE_DECISION}
';

	var $html_summary = 
'<span style="background-color: {COLOR}; border: solid green; padding-right: 1em; padding-left: 1em">{SUMMARY}</span>&nbsp;&nbsp;
<span style="color:red">{SUMMARY_DETAIL}</span>';

	var $html_a = 
'<a title="{TITLE}" target="_new" href="{HREF}">{TITLE}</a>';
	
	var $html_button_make_decision = 
'<p align="center">
<input value="Make Decisions" type="submit" name="make_decision" />
</p>';
	
	var $html_detail = 
'<h4>{DETAIL_TITLE}</h4>
<div id="{DIV_ID}" style="margin-top:1em">
	{DETAIL}
</div>';

	var $aValidator;                  // from parameter. instance of AccessibilityValidator
	var $userLinkID;                  // from parameter. user_links.user_link_id
	var $guidelineIDs;                // from parameter. array of guideline IDs
	var $htmlRpt;                     // instance of HTMLRpt. Generate error detail
	
	var $numOfErrors;                 // number of errors
	var $numOfLikelyProblems;         // number of likely problems
	var $numOfFailLikelyProblems;     // number of likely problems with fail decision or no decision
	var $numOfPotentialProblems;      // number of potential problems
	var $numOfFailPotentialProblems;  // number of potential problems with fail decision or no decision
	var $numOfNoDecision;             // number of problems with choice "no decision"
	
	var $guidelineStr;                // used to replace $html_main.{GUIDELINE}. Generated by setGuidelineStr()
	var $summaryStr;                  // used to replace $html_main.{SUMMARY}. Generated by setSummaryStr()
	var $mainStr;                     // main output. Generated by setMainStr()

	/**
	* Constructor
	* @access  public
	* @param   $errors : a instance of AccessibilityValidator. Call $aValidator->validate(); before pass in the instance
	*          $guidelineIDs: array of guideline IDs
	* @return  web service html output
	* @author  Cindy Qi Li
	*/
	function HTMLWebServiceOutput($aValidator, $userLinkID, $guidelineIDs)
	{
		$this->aValidator = $aValidator;
		$this->guidelineIDs = $guidelineIDs;
		$this->userLinkID = $userLinkID;
		
		$this->htmlRpt = new HTMLRpt($aValidator->getValidationErrorRpt(), $userLinkID);
		$this->htmlRpt->setAllowSetDecisions('true');
		$this->htmlRpt->generateHTMLRpt();
		
		$this->numOfErrors = $this->htmlRpt->getNumOfErrors();
		$this->numOfNoDecision = $this->htmlRpt->getNumOfNoDecisions();

		$this->numOfLikelyProblems = $this->htmlRpt->getNumOfLikelyProblems();
		$this->numOfFailLikelyProblems = $this->htmlRpt->getNumOfLikelyWithFailDecisions();
		
		$this->numOfPotentialProblems = $this->htmlRpt->getNumOfPotentialProblems();
		$this->numOfFailPotentialProblems = $this->htmlRpt->getNumOfPotentialWithFailDecisions();
		
		// setGuidelineStr() & setSummaryStr() must be called before setMainStr()
		$this->setGuidelineStr();       // set $this->guidelineStr
		$this->setSummaryStr();         // set $this->summaryStr
		$this->setMainStr();            // set $this->mainStr
	}
	
	/**
	* set guideline string used to replace $html_main.{GUIDELINE}
	* @access  private
	* @param   none
	* @return  set $guidelineStr
	* @author  Cindy Qi Li
	*/
	private function setGuidelineStr()
	{
		if (!is_array($this->guidelineIDs)) return '';
		
		$guidelineDAO = new GuidelinesDAO();
		
		foreach ($this->guidelineIDs as $gid)
			$gids .= $gid . ",";
		
		$gids = substr($gids, 0, -1);
		$guidelinesDAO = new GuidelinesDAO();
		$rows = $guidelinesDAO->getGuidelineByIDs($gids);
		
		unset($this->guidelineStr);
		if (is_array($rows))
		{
			foreach ($rows as $id => $row)
			{
				$this->guidelineStr .= str_replace(array('{TITLE}','{HREF}'),
				                           array($row['title']._AC('link_open_in_new'),
				                                 AC_BASE_HREF.'guideline/view_guideline.php?id='.$row['guideline_id']),
				                           $this->html_a). "&nbsp;&nbsp;";
			}
		}
	}

	/**
	* set summary string used to replace $html_main.{SUMMARY}
	* @access  private
	* @param   none
	* @return  set $summaryStr
	* @author  Cindy Qi Li
	*/
	private function setSummaryStr()
	{
		// generate $html_summary.{SUMMARY}
		if ($this->numOfErrors > 0)
		{
			$summary = _AC('fail');
			$color = 'red';
		}
		else if ($this->numOfFailLikelyProblems + $this->numOfFailPotentialProblems > 0)
		{
			$summary = _AC('conditional_pass');
			$color = 'yellow';
		}
		else
		{
			$summary = _AC('pass');
			$color = 'green';
		}

		// generate $html_summary.{SUMMARY_DETAIL}
		$summary_detail = '<span style="font-weight: bold;">';
		if ($this->numOfErrors > 0) $summary_detail .= $this->numOfErrors. ' ' ._AC('errors').'&nbsp;&nbsp;';
		if ($this->numOfFailLikelyProblems > 0) 
		{
			$summary_detail .= $this->numOfFailLikelyProblems.' '._AC('likely_problems').'&nbsp;&nbsp;';
		}
		if ($this->numOfFailPotentialProblems > 0)
		{
			$summary_detail .= $this->numOfFailPotentialProblems.' '._AC('potential_problems').'&nbsp;&nbsp;';
		}
		$summary_detail .= '</span>';
		
		$this->summaryStr = str_replace(array('{COLOR}', '{SUMMARY}', '{SUMMARY_DETAIL}'),
		                                array($color, $summary, $summary_detail),
		                                $this->html_summary);
	}

	/**
	* set main report
	* @access  private
	* @param   none
	* @return  set main report
	* @author  Cindy Qi Li
	*/
	private function setMainStr()
	{
		// get $html_main.{SESSIONID}
		$userLinksDAO = new UserLinksDAO();
		$row = $userLinksDAO->getByUserLinkID($this->userLinkID);
		$sessionID = $row['last_sessionID'];
		
		if ($this->numOfErrors > 0)
		{
			$detail_error = str_replace(array('{DETAIL_TITLE}', '{DIV_ID}', '{DETAIL}'),
			                            array(_AC('errors'), 'errors', $this->htmlRpt->getErrorRpt()),
			                            $this->html_detail);
		}
		
		if ($this->numOfLikelyProblems > 0)
		{
			$detail_likely = str_replace(array('{DETAIL_TITLE}', '{DIV_ID}', '{DETAIL}'),
			                            array(_AC('likely_problems'), 'likely_problems', $this->htmlRpt->getLikelyProblemRpt()),
			                            $this->html_detail);
		}

		if ($this->numOfPotentialProblems > 0)
		{
			$detail_potential = str_replace(array('{DETAIL_TITLE}', '{DIV_ID}', '{DETAIL}'),
			                            array(_AC('potential_problems'), 'potential_problems', $this->htmlRpt->getPotentialProblemRpt()),
			                            $this->html_detail);
		}
		
		// generate $html_main.{DETAIL}
		if ($detail_error <> '' || $detail_likely <> '' || $detail_potential <> '')
		{
			$detail = '<h3>'._AC("accessibility_review").'</h3>'."\n".$detail_error.$detail_likely.$detail_potential;
		}
		
		// set display of "make decision" button
		if ($this->numOfNoDecision > 0) $button_make_decision = $this->html_button_make_decision;
		
		// set main string
		$this->mainStr = str_replace(array('{SESSIONID}', 
		                                   '{SUMMARY}', 
		                                   '{GUIDELINE}',
		                                   '{DETAIL}', 
		                                   '{BUTTON_MAKE_DECISION}'),
			                         array($sessionID,
			                               $this->summaryStr,
			                               $this->guidelineStr,
			                               $detail,
			                               $button_make_decision),
			                         $this->html_main);
	}

	/**
	* return main report
	* @access  public
	* @param   none
	* @return  return main report
	* @author  Cindy Qi Li
	*/
	public function getWebServiceOutput()
	{
		return $this->mainStr;
	}
}

?>