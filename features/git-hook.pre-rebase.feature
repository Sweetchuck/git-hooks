Feature: Test for pre-rebase hook.

  Background:
    Given I create a "basic" project in "p-01" directory
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """

  Scenario Outline: Current branch - Positive & Negative
    And I run git checkout -b "<upstream>"
    And I run git branch "<current_branch>"
    And I commit a new "foo.txt" file with message "Add foo.txt" and content:
    """
    @todo
    """
    And I run git checkout "<current_branch>"
    And I run git rebase "<upstream>"
    Then the exit code should be <exit_code>
    And the stdOut should contains the following text:
    """
    >  RoboFile::githookPreRebase is called
    >  Current branch: "<current_branch>"
    >  Upstream: "<upstream>"
    >  Subject branch: ""
    """
    Examples:
      | current_branch | upstream  | exit_code |
      | feature-1      | protected | 0         |
      | protected      | feature-1 | 1         |

  Scenario Outline: Other branch - Positive & Negative
    And I run git checkout -b "<upstream>"
    And I run git branch "<subject_branch>"
    And I commit a new "foo.txt" file with message "Add foo.txt" and content:
    """
    @todo
    """
    And I run git checkout "master"
    And I run git rebase "<upstream>" "<subject_branch>"
    Then the exit code should be <exit_code>
    And the stdOut should contains the following text:
    """
    >  RoboFile::githookPreRebase is called
    >  Current branch: "master"
    >  Upstream: "<upstream>"
    >  Subject branch: "<subject_branch>"
    """
    Examples:
      | subject_branch | upstream  | exit_code |
      | feature-1      | protected | 0         |
      | protected      | feature-1 | 1         |
