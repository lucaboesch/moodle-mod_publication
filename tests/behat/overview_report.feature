@mod @mod_publication
Feature: Testing overview integration in publication activity
  In order to summarize the publication activity
  As a user
  I need to be able to see the publication activity overview

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Vinnie    | Student1 | student1@example.com |
      | student2 | Ann       | Student2 | student2@example.com |
      | teacher1 | Darrell   | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name | course | idnumber |
      | A    | C1     | C1G1     |
      | B    | C1     | C1G2     |
      | C    | C1     | C1G3     |
      | D    | C1     | C1G4     |
    And the following "activities" exist:
      | activity    | name             | intro                        | course | idnumber     |
      | publication | Student folder 1 | Student folder 1 for testing | C1     | publication1 |

  @javascript
  Scenario: The publication activity overview report should generate log events
    Given the site is running Moodle version 5.0 or higher
    And I am on the "Course 1" "course > activities > publication" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'publication'"

  @javascript
  Scenario: The publication activity index redirect to the activities overview
    Given the site is running Moodle version 5.0 or higher
    When I am on the "C1" "course > activities > publication" page logged in as "admin"
    Then I should see "An overview of all activities in the course"
    And I should see "Name" in the "publication_overview_collapsible" "region"
    And I should see "Action" in the "publication_overview_collapsible" "region"