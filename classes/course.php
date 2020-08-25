<?php
namespace SalesforceCourseSync;


class Course {
  public $salesforce_id;
  public $salesforce_data;
  public $updated_at;

	public function __construct($data) {
    $this->salesforce_id = $data->salesforce_id;
    $this->salesforce_data = json_decode($data->salesforce_data);
    $this->updated_at = $data->updated_at;

    $this->name = $this->salesforce_data->Name;
    $this->lecture_cost = $this->format_currency(floatval($this->salesforce_data->Web_Price_Lecture__c));
    $this->outreach_cost = $this->format_currency(floatval($this->salesforce_data->Web_Price_Outreach));
    $this->total_cost = $this->format_currency(floatval($this->salesforce_data->Web_Price_Lecture__c) + floatval($this->salesforce_data->Web_Price_Outreach));
    $this->start_date = $this->salesforce_data->StartDate__c;
    $this->grad_date = $this->salesforce_data->Graduation_Date__c;
    $this->departure_date = $this->salesforce_data->Departure_Date__c;
    $this->year = $this->salesforce_data->CourseCodeYear__c;
    $this->quarter = $this->salesforce_data->CourseCodeQuarter__c;
    $this->active = $this->salesforce_data->ShowInOnlineApplicationSystem__c == '1';
  }

  static function get_tablename() {
    global $wpdb;
    return "{$wpdb->prefix}salesforce_courses";
  }

  static function all() {
    global $wpdb;
    $results = $wpdb->get_results("SELECT * from {$wpdb->prefix}salesforce_courses");
    return array_map(function($result) { return new Course($result); }, $results);
  }

  static function find($id) {
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}salesforce_courses where salesforce_id=\"%s\"", $id));
    if (!$result)
      return false;
    return new Course($result);
  }

  private function format_currency($number) {
    return '$' . number_format_i18n($number, 2);
  }
}
