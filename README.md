# ClaimFromAnotherProject

Claim From Another Project is an EM used to grab the 'next' matching record from an external project and return values back to the main project.  It is similar to how REDCap's allocation-based randomization works.  In fact, Claim From Another Project could be used in lieu of the Randomization module if one so desired...

The motivations for this em were many projects:
- A giftcard project that uses similar logic to pull the next available giftcard when someone completes some surveys
- A project where we were emailing application-registration files (ssh keys).  When someone became 'eligible' in the main study - we pulled a ssh key from a central database and assigned it to the record.  After this we sent an email via an alert so they received the key as an attachment.
- After randomization in a scenario where the main coordinators are blinded - we use this to replace the default Arm A and Arm B randomization outputs with aliases that are unique to each record and can only be translated by the pharmacist issueing the drug for the study.  This is actually the best use thus far for the EM.

With the latest enhancements, you can now incorporate smart-variables like DAG name into the lookup logic.

Good luck - and reach out to the consortium if you are having setup difficulties.
