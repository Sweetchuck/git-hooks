Feature: Test for pre-push hook.

  @hook-pre-push
  Scenario Outline: Single branch - Positive & Negative
    Given I initialize a bare Git repo in directory "b-01"
    And I am in the ".." directory
    And I create a "basic" project in "p-01" directory
    And I run git add remote "origin" "../b-01"
    And I create a "README.md" file
    And I run git add "README.md"
    And I run git commit -m <commit_msg>
    And the number of commits is 1
    And I run git push "origin" "master"
    Then the exit code should be <exit_code>
    And the stdOut should contains the following text:
    """
    ➜  RoboFile::githookPrePush is called
    ➜  Remote name: origin
    ➜  Remote URI: ../b-01
    """
    Examples:
      | commit_msg | exit_code |
      | Valid      | 0         |
      | Invalid    | 1         |
