<?php
namespace SalesforceCourseSync;


class Shortcodes {
  public $field_options = ['lecture_cost', 'outreach_cost', 'total_cost', 'start_date', 'grad_date', 'departure_date', 'year', 'quarter', 'active'];

	public function __construct() {
    add_shortcode( 'salesforce_course_data', [$this, 'salesforce_course_data'] );
  }

  // [salesforce_course_data course_id="1234" field="cost"]

  public function salesforce_course_data($atts) {
    if (!isset($atts['field']) || !in_array($atts['field'], $this->field_options))
      return sprintf("salesforce_course_data shortcode requires a 'field' parameter with one of the following values: %s", implode(', ', $this->field_options));

    $course = Course::find($atts['course_id']);
    if (!$course)
      return "salesforce_course_data failed: could not find a course with the ID: {$atts['course_id']}";

    return $course->{$atts['field']};
  }
}
