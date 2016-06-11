Feature: Test for pre-rebase hook.

  Scenario Outline: Positive & Negative
    Given I initialize a bare Git repo in directory "b-01"
    And I am in the ".." directory
    And I create a "basic" project in "p-01" directory
    And I run git add remote "origin" "../b-01"
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """
    And I run git checkout -b "<base_branch>"
    And I run git branch "<feature_branch>"
    And I commit a new "foo.txt" file with message "Add foo.txt" and content:
    """
    @todo
    """
    And I run git checkout "<feature_branch>"
    And I run git rebase "<base_branch>"
    Then the exit code should be <exit_code>
    And the stdOut should contains the following text:
    """
    ➜  RoboFile::githookPreRebase is called
    ➜  Base: "<base_branch>"
    ➜  Subject branch: ""
    ➜  Current branch: "<feature_branch>"
    """
    Examples:
      | base_branch | feature_branch | exit_code |
      | protected   | feature-1      | 0         |
      | feature-1   | protected      | 1         |
