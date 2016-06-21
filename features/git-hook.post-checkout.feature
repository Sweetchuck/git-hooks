Feature: Test for post-checkout

  Background:
    Given I create a "basic" project in "p-01" directory
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """
    And I run git branch "feature-1"

  Scenario: Branch checkout.
    And I run git checkout "feature-1"
    Then the stdErr should contains the following text:
    """
    ➜  RoboFile::githookPostCheckout is called
    ➜  Old ref: "OLD_REF"
    ➜  New ref: "NEW_REF"
    ➜  Branch checkout: "yes"
    """

  Scenario: File checkout.
    And I commit a new "CONTRIBUTE.md" file with message "WIP" and content:
    """
    @todo
    """
    And I run git checkout "feature-1"
    And I run git checkout "master" -- "CONTRIBUTE.md"
    Then the stdErr should contains the following text:
    """
    ➜  RoboFile::githookPostCheckout is called
    ➜  Old ref: "OLD_REF"
    ➜  New ref: "NEW_REF"
    ➜  Branch checkout: "no"
    """
