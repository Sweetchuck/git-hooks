Feature: Test for post-receive hook.

  Scenario: Single branch
    Given I initialize a bare Git repo in directory "b-01"
    And I am in the ".." directory
    And I create a "basic" project in "p-01" directory
    And I run git add remote "origin" "../b-01"
    And I create a "README.md" file
    And I run git add "README.md"
    And I run git commit -m "Initial commit"
    And the number of commits is 1
    And I run git push origin master
    Then the exit code should be 0
    And the stdErr should contains the following text:
    """
    remote: >  RoboFile::githookPostReceive is called
    remote: >  stdInput line 1: "OLD_REV" "NEW_REV" "refs/heads/master"
    remote: >  Lines in stdInput: "1"
    """
