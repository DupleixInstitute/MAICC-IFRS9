<?php
$Commitment                          = $row["Commitment"];// Rating
$Integrity                           = $row["Integrity"];// Rating
$InformationQuality                  = $row["InformationQuality"];// Rating
$Leadership                          = $row["Leadership"];// Rating
$Strategy                            = $row["Strategy"];// Rating
$Structure                           = $row["Structure"];// Rating
$Management                          = $row["Management"];// Rating
$SuccessionPlanning                  = $row["SuccessionPlanning"];// Rating
$OrganisationalPlanning              = $row["OrganisationalPlanning"];// Rating
$CommitmentComment                   = $row["CommitmentComment"];// Justification
$IntegrityComment                    = $row["IntegrityComment"];// Justification
$InformationQualityComment           = $row["InformationQualityComment"];// Justification
$LeadershipComment                   = $row["LeadershipComment"];// Justification
$StrategyComment                     = $row["StrategyComment"];// Justification
$StructureComment                    = $row["StructureComment"];// Justification
$ManagementComment                   = $row["ManagementComment"];// Justification
$SuccessionPlanningComment           = $row["SuccessionPlanningComment"];// Justification
$OrganisationalPlanningComment       = $row["OrganisationalPlanningComment"];// Justification
$CommitmentReviewComment             = $row["CommitmentReviewComment"];// ReviewComment
$IntegrityReviewComment              = $row["IntegrityReviewComment"];// ReviewComment
$InformationQualityReviewComment     = $row["InformationQualityReviewComment"];// ReviewComment
$LeadershipReviewComment             = $row["LeadershipReviewComment"];// ReviewComment
$StrategyReviewComment               = $row["StrategyReviewComment"];// ReviewComment
$StructureReviewComment              = $row["StructureReviewComment"];// ReviewComment
$ManagementReviewComment             = $row["ManagementReviewComment"];// ReviewComment
$SuccessionPlanningReviewComment     = $row["SuccessionPlanningReviewComment"];// ReviewComment
$OrganisationalPlanningReviewComment = $row["OrganisationalPlanningReviewComment"];// ReviewComment
			 
$username                            = $row["username"];    
?>
  
<script>
		
       localStorage.Commitment                          = "<?php echo $Commitment?>";
       localStorage.Integrity                           = "<?php echo $Integrity?>";
       localStorage.InformationQuality                  = "<?php echo $InformationQuality?>";
       localStorage.Leadership                          = "<?php echo $Leadership?>";
       localStorage.Strategy                            = "<?php echo $Strategy?>";
       localStorage.Structure                           = "<?php echo $Structure?>";
       localStorage.Management                          = "<?php echo $Management?>";
       localStorage.SuccessionPlanning                  = "<?php echo $SuccessionPlanning?>";
       localStorage.OrganisationalPlanning              = "<?php echo $OrganisationalPlanning?>";
       localStorage.CommitmentComment                   = "<?php echo $CommitmentComment?>";
       localStorage.IntegrityComment                    = "<?php echo $IntegrityComment?>";
       localStorage.InformationQualityComment           = "<?php echo $InformationQualityComment?>";
       localStorage.LeadershipComment                   = "<?php echo $LeadershipComment?>";
       localStorage.StrategyComment                     = "<?php echo $StrategyComment?>";
       localStorage.StructureComment                    = "<?php echo $StructureComment?>";
       localStorage.ManagementComment                   = "<?php echo $ManagementComment?>";
       localStorage.SuccessionPlanningComment           = "<?php echo $SuccessionPlanningComment?>";
       localStorage.OrganisationalPlanningComment       = "<?php echo $OrganisationalPlanningComment?>";
       localStorage.CommitmentReviewComment             = "<?php echo $CommitmentReviewComment?>";
       localStorage.IntegrityReviewComment              = "<?php echo $IntegrityReviewComment?>";
       localStorage.InformationQualityReviewComment     = "<?php echo $InformationQualityReviewComment?>";
       localStorage.LeadershipReviewComment             = "<?php echo $LeadershipReviewComment?>";
       localStorage.StrategyReviewComment               = "<?php echo $StrategyReviewComment?>";
       localStorage.StructureReviewComment              = "<?php echo $StructureReviewComment?>";
       localStorage.ManagementReviewComment             = "<?php echo $ManagementReviewComment?>";
       localStorage.SuccessionPlanningReviewComment     = "<?php echo $SuccessionPlanningReviewComment?>";
       localStorage.OrganisationalPlanningReviewComment = "<?php echo $OrganisationalPlanningReviewComment?>";
		 
	   alert ("Management Analysis Client Data Loaded"); 
         
</script>
