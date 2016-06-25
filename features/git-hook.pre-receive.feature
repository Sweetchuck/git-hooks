Feature: Test for pre-receive hook.

  Scenario Outline: Single branch - Positive & Negative
    Given I initialize a bare Git repo in directory "b-01"
    And I am in the ".." directory
    And I create a "basic" project in "p-01" directory
    And I run git add remote "origin" "../b-01"
    And I create a "README.md" file
    And I run git add "README.md"
    And I run git commit -m "Initial commit"
    And the number of commits is 1
    And I run git push "origin" "master:<remote_branch>"
    Then the exit code should be <exit_code>
    And the stdErr should contains the following text:
    """
    remote: ➜  RoboFile::githookPreReceive is called
    remote: ➜  Ref: 'refs/heads/<remote_branch>'
    remote: ➜  Lines in stdInput: '1'
    """
    Examples:
      | remote_branch       | exit_code |
      | master              | 0         |
      | invalid-pre-receive | 1         |
