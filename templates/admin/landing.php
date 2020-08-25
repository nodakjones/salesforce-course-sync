<h2>Displaying Course Data</h2>
<p>When creating courses in Wordpress, you can display information from a related Salesforce Course using the following shortcode in any 'Text' block:</p>
<ul>
  <li>
    [salesforce_course_data course_id="a0q18000001JELtAAO" field="end_date"]
  </li>
</ul>
<p>When you use this shortcode, the information from Salesforce will automatically be updated when it is updated on Salesforce.</p>
<p>Make sure that you specify the correct course_id. You can see a list of all the courses that are available in Salesforce by going to the courses tab above.</p>
<p>The 'field' parameter allows you to specify which field you would like to display from the Salesforce Course. The available fields are ('cost', 'start_date', and 'end_date').</p>

<h2>How it works</h2>
<p>Once per hour, the plugin requests a list of all the courses from the Salesforce API. When it receives a response, it saves a copy of the course data locally for usage across the site. You can see the course information that is saved currently on the Courses page</p>
<p>If courses are not being updated, you can check the Logs tab to see if something is going wrong with the API calls.</p>