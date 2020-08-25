<?php
namespace SalesforceCourseSync;

class SalesforceImporter {
  protected $salesforce;

  protected $salesforce_course_object_name = 'CourseType__c';
  protected $salesforce_course_fields_to_pull = ['Id', 'Name', 'Course_Code__c'];

  protected $salesforce_course_date_object_name = 'BP_CourseEventOutreach__c';
  protected $salesforce_course_date_fields_to_pull = ['Id', 'Name', 'StartDate__c', 'EndDate__c', 'Graduation_Date__c', 'Languages__c', 'Web_Price_Lecture__c', 'Web_Price_Outreach__c', 'ShowInOnlineApplicationSystem__c', 'CourseCodeYear__c', 'CourseCodeQuarter__c', 'Deposit__c', 'CalculatedCourseCode__c', 'U_of_N_Credits__c', 'CourseType__c', 'Canceled__c', 'RecordType.Name', '(select Id, TracksDegrees__c, TracksDegrees__r.Name from CoursesAndTracksRelation__r )'];

  protected $existing_courses_by_sf_id = array();
  protected $existing_course_dates_by_sf_id = array();

  public function __construct($salesforce) {
    $this->salesforce = $salesforce;
    $this->register_cron();
  }

  private function register_cron() {
    add_action('refresh_salesforce_courses', [$this, 'refresh_salesforce_courses']);
    if (!wp_next_scheduled('refresh_salesforce_courses')) {
      wp_schedule_event(time(), 'hourly', 'refresh_salesforce_courses');
    }
  }

  public function refresh_salesforce_courses() {
    try {
      return $this->fetch_all();
    } catch (SalesforceException $e) {
      $var = Logging::add('Salesforce Fetch Failed', $e->getMessage());
    }
  }

  public function available_fields() {
    $describe = $this->salesforce['sfapi']->object_describe($this->salesforce_course_date_object_name);
    return array_map(function($elem) { return $elem['name']; }, $describe['data']['fields']);
  }

  /*
    Load all courses so we can tie them to the course date
  */
  public function get_existing_courses() {

    global $wpdb;

    $results = $wpdb->get_results("
      select ID, meta_value
      from {$wpdb->prefix}posts
      join {$wpdb->prefix}postmeta on {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
      where {$wpdb->prefix}posts.post_type = 'courses'
      and {$wpdb->prefix}posts.post_status = 'publish'
      and {$wpdb->prefix}postmeta.meta_key = 'sf_course_type_id'
    ");

    foreach ($results as $result) {
      $this->existing_courses_by_sf_id[$result->meta_value] = $result->ID;
    }

  }

  public function get_existing_course_dates() {

    global $wpdb;

    $results = $wpdb->get_results("
      select ID, meta_value
      from {$wpdb->prefix}posts
      join {$wpdb->prefix}postmeta on {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
      where {$wpdb->prefix}posts.post_type = 'course-dates'
      and {$wpdb->prefix}postmeta.meta_key = 'sf_course_id'
    ");

    foreach ($results as $result) {
      $this->existing_course_dates_by_sf_id[$result->meta_value] = $result->ID;
    }

  }

  public function import_wp_course_date($record) {

    global $wpdb;

    // get SF course id
    // $existing_course_date = $wpdb->get_results("SELECT salesforce_id, wp_post_id FROM {$wpdb->prefix}salesforce_courses where salesforce_id = '{$record['Id']}'");
    $existing_course_date = $this->existing_course_dates_by_sf_id[$record['Id']];
    $course = $this->existing_courses_by_sf_id[$record['CourseType__c']];

    // echo '<pre>';
    // var_dump($record);
    // echo '</pre>';

    $is_track = $record['CoursesAndTracksRelation__r'] != null;

    $wp_post = array(
      'ID' => $existing_course_date ? $existing_course_date : 0,
      'post_title' => $is_track ? $record['CoursesAndTracksRelation__r']['records'][0]['TracksDegrees__r']['Name'] : $record['Name'],
      'post_status' => 'publish',
      'post_type' => 'course-dates',
      'meta_input' => array(
        'start_date' => $record['StartDate__c'],
        'end_date' => $record['EndDate__c'],
        'year' => intval($record['CourseCodeYear__c']),
        'quarter' => intval(str_replace('Q', '', $record['CourseCodeQuarter__c'])),
        'credits' => $record['U_of_N_Credits__c'],
        'course_code' => $record['CalculatedCourseCode__c'],
        'deposit' => $record['Deposit__c'],
        'lecture_cost' => $record['Web_Price_Lecture__c'], //floatval(preg_replace('/(\$|,)/', '', $record['Web_Price_Lecture__c'])),
        'outreach_cost' => $record['Web_Price_Outreach__c'], //floatval(preg_replace('/(\$|,)/', '', $record['Web_Price_Outreach__c'])),
        'course' => $course ? $course : null,
        'sf_course_type_id' => $record['CourseType__c'],
        'sf_course_id' => $record['Id'],
        'languages' => $record['Languages__c'],
        'show_in_online_application_system' => $record['ShowInOnlineApplicationSystem__c'],
        'canceled' => $record['Canceled__c'],
        'record_type' => $record['RecordType']['Name'],
        'duration' => $is_track ? '9 months' : '3 months',
        'is_track' => $is_track,
        'track_id' => $is_track ? $record['CoursesAndTracksRelation__r']['records'][0]['TracksDegrees__c'] : ''
      )
    );

    $wp_post_id = wp_insert_post($wp_post);

    return $wp_post_id;

  }

  public function import_course_dates() {

    global $wpdb;

    $query = new SalesforceQuery($this->salesforce_course_date_object_name);
    // Only pull future courses (there are thousands of past courses, and we don't need to display them)
    $query->add_condition('StartDate__c', date('Y-m-d'), '>');
    $query->add_condition('Status__c', "'Online'");
    $query->add_condition('RecordType.Name', "'2 - U of N, Kona'");

    $query->fields = $this->salesforce_course_date_fields_to_pull;
    $result = $this->salesforce['sfapi']->query($query);

    if ($result['code'] != 200)
      return $result;

    $table = Course::get_tablename();
    $values = [];

    $added_tracks = array();
    foreach ($result['data']['records'] as $record) {

      if(isset($record['CoursesAndTracksRelation__r']['records'][0]['TracksDegrees__c'])){
        $track_id = $record['CoursesAndTracksRelation__r']['records'][0]['TracksDegrees__c'];
        if(in_array($track_id, $added_tracks)){
          continue;
        }
        $added_tracks[] = $track_id;
      }

      $wp_id = $this->import_wp_course_date($record);

      if($wp_id == 0) {
        return 'Something went wrong with saving a course dates';
      }

      $values[] = '(' . join(', ', [
        $wpdb->prepare('%s', $wp_id),
        $wpdb->prepare('%s', $record['Id']),
        $wpdb->prepare('%s', json_encode($record)),
        'CURTIME()'
      ]) . ')';
    }

    $values = join(', ', $values);
    $query = "REPLACE INTO {$table} (wp_post_id, salesforce_id, salesforce_data, updated_at) VALUES {$values}";
    $query_result = $wpdb->query($query);

    if ($query_result) {
      return true;
    } else {
      return 'Failed to insert some values into the database. The failing query is: ' . $query;
    }

  }

  public function import_and_attach_tracks(){

    global $wpdb;

    // get track id's
    $courses_with_tracks = $wpdb->get_results("
      select post_id, meta_value
      from {$wpdb->prefix}postmeta
      where {$wpdb->prefix}postmeta.meta_key = 'track_id'
      and {$wpdb->prefix}postmeta.meta_value != ''
    ");

    $tracks = array();
    foreach ($courses_with_tracks as $course_with_track) {
      $tracks[] = $course_with_track->meta_value;
    }

    // echo '<pre>';
    // var_dump($tracks);

    $query = new SalesforceQuery('BP_CoursesAndTracksRelation__c');
    $query->add_condition('TracksDegrees__c', $tracks, 'in');
    $query->fields = array('Id', 'TracksDegrees__c', 'CourseEventOutreach__r.Name' , 'CourseEventOutreach__r.StartDate__c' , 'CourseEventOutreach__r.EndDate__c', 'CourseEventOutreach__c');
    $result = $this->salesforce['sfapi']->query($query);

    $sub_courses_by_track = array();
    foreach ($result['data']['records'] as $track_relation) {
      if(isset($sub_courses_by_track[$track_relation['TracksDegrees__c']]) == false) {
        $sub_courses_by_track[$track_relation['TracksDegrees__c']] = array(); 
      }
      $sub_courses_by_track[$track_relation['TracksDegrees__c']][] = array(
        'sf_id' => $track_relation['CourseEventOutreach__c'],
        'name' => $track_relation['CourseEventOutreach__r']['Name'],
        'start_date' => $track_relation['CourseEventOutreach__r']['StartDate__c'],
        'end_date' => $track_relation['CourseEventOutreach__r']['EndDate__c']
      );
    }

    // echo "saving course dates  \n";

    // echo '<pre>';

    foreach ($courses_with_tracks as $course_with_track) {

      // var_dump($sub_courses_by_track);

      // var_dump($course_with_track->meta_value);
      // var_dump($sub_courses_by_track[$course_with_track->meta_value]);

      $sub_courses = $sub_courses_by_track[$course_with_track->meta_value];

      usort($sub_courses, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
      });

      update_field('start_date', $sub_courses[0]['start_date'], $course_with_track->post_id);
      update_field('end_date', $sub_courses[count($sub_courses) - 1]['end_date'], $course_with_track->post_id);

      update_field('track_course_dates', $sub_courses, $course_with_track->post_id);

    }

    return true;

  }

  public function fetch_all() {

    $this->get_existing_courses();
    $this->get_existing_course_dates();

    // var_dump($this->existing_courses_by_sf_id);

    $imported_course_dates = $this->import_course_dates();
    if($imported_course_dates != true) {
      return $imported_course_dates;
    }

    // fetch course dates by track and add them to the course dates
    $imported_tracks = $this->import_and_attach_tracks();
    if($imported_tracks != true) {
      return $imported_tracks;
    }


    return true;

  }
}
