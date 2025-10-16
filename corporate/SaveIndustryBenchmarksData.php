
<html>

<head>
<Title>Saving Industry Benchmarks Data</Title>
</head>

<body>
<?php

include 'InitialiseIndustryBenchmarksVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$CurrentRatioBenchmarkType                       =  str_replace(",","",$_POST['CurrentRatioBenchmarkType']) ;
$CurrentRatioGlobalAverage                       =  str_replace(",","",$_POST['CurrentRatioGlobalAverage']) ;
$CurrentRatioBenchmark_Trade                     =  str_replace(",","",$_POST['CurrentRatioBenchmark_Trade']) ;
$CurrentRatioBenchmark_Finance                   =  str_replace(",","",$_POST['CurrentRatioBenchmark_Finance']) ;
$CurrentRatioBenchmark_RealEstate                =  str_replace(",","",$_POST['CurrentRatioBenchmark_RealEstate']) ;
$CurrentRatioBenchmark_Manufacturing             =  str_replace(",","",$_POST['CurrentRatioBenchmark_Manufacturing']) ;
$CurrentRatioBenchmark_Construction              =  str_replace(",","",$_POST['CurrentRatioBenchmark_Construction']) ;
$CurrentRatioBenchmark_Agriculture               =  str_replace(",","",$_POST['CurrentRatioBenchmark_Agriculture']) ;
$CurrentRatioBenchmark_Parastatals                =  str_replace(",","",$_POST['CurrentRatioBenchmark_Parastatals']) ;
$CurrentRatioBenchmark_Transport                 =  str_replace(",","",$_POST['CurrentRatioBenchmark_Transport']) ;
$CurrentRatioBenchmark_Mining                    =  str_replace(",","",$_POST['CurrentRatioBenchmark_Mining']) ;
$CurrentRatioBenchmark_DateUpdated               =  str_replace(",","",$_POST['CurrentRatioBenchmark_DateUpdated']) ;
$CurrentRatioBenchmarkComment                    =  str_replace(",","",$_POST['CurrentRatioBenchmarkComment']) ;
$QuickRatioBenchmarkType                         =  str_replace(",","",$_POST['QuickRatioBenchmarkType']) ;
$QuickRatioGlobalAverage                         =  str_replace(",","",$_POST['QuickRatioGlobalAverage']) ;
$QuickRatioBenchmark_Trade                       =  str_replace(",","",$_POST['QuickRatioBenchmark_Trade']) ;
$QuickRatioBenchmark_Finance                     =  str_replace(",","",$_POST['QuickRatioBenchmark_Finance']) ;
$QuickRatioBenchmark_RealEstate                  =  str_replace(",","",$_POST['QuickRatioBenchmark_RealEstate']) ;
$QuickRatioBenchmark_Manufacturing               =  str_replace(",","",$_POST['QuickRatioBenchmark_Manufacturing']) ;
$QuickRatioBenchmark_Construction                =  str_replace(",","",$_POST['QuickRatioBenchmark_Construction']) ;
$QuickRatioBenchmark_Agriculture                 =  str_replace(",","",$_POST['QuickRatioBenchmark_Agriculture']) ;
$QuickRatioBenchmark_Parastatals                  =  str_replace(",","",$_POST['QuickRatioBenchmark_Parastatals']) ;
$QuickRatioBenchmark_Transport                   =  str_replace(",","",$_POST['QuickRatioBenchmark_Transport']) ;
$QuickRatioBenchmark_Mining                      =  str_replace(",","",$_POST['QuickRatioBenchmark_Mining']) ;
$QuickRatioBenchmark_DateUpdated                 =  str_replace(",","",$_POST['QuickRatioBenchmark_DateUpdated']) ;
$QuickRatioBenchmarkComment                      =  str_replace(",","",$_POST['QuickRatioBenchmarkComment']) ;
$DebtorDaysBenchmarkType                         =  str_replace(",","",$_POST['DebtorDaysBenchmarkType']) ;
$DebtorDaysGlobalAverage                         =  str_replace(",","",$_POST['DebtorDaysGlobalAverage']) ;
$DebtorDaysBenchmark_Trade                       =  str_replace(",","",$_POST['DebtorDaysBenchmark_Trade']) ;
$DebtorDaysBenchmark_Finance                     =  str_replace(",","",$_POST['DebtorDaysBenchmark_Finance']) ;
$DebtorDaysBenchmark_RealEstate                  =  str_replace(",","",$_POST['DebtorDaysBenchmark_RealEstate']) ;
$DebtorDaysBenchmark_Manufacturing               =  str_replace(",","",$_POST['DebtorDaysBenchmark_Manufacturing']) ;
$DebtorDaysBenchmark_Construction                =  str_replace(",","",$_POST['DebtorDaysBenchmark_Construction']) ;
$DebtorDaysBenchmark_Agriculture                 =  str_replace(",","",$_POST['DebtorDaysBenchmark_Agriculture']) ;
$DebtorDaysBenchmark_Parastatals                  =  str_replace(",","",$_POST['DebtorDaysBenchmark_Parastatals']) ;
$DebtorDaysBenchmark_Transport                   =  str_replace(",","",$_POST['DebtorDaysBenchmark_Transport']) ;
$DebtorDaysBenchmark_Mining                      =  str_replace(",","",$_POST['DebtorDaysBenchmark_Mining']) ;
$DebtorDaysBenchmark_DateUpdated                 =  str_replace(",","",$_POST['DebtorDaysBenchmark_DateUpdated']) ;
$DebtorDaysBenchmarkComment                      =  str_replace(",","",$_POST['DebtorDaysBenchmarkComment']) ;
$CreditorDaysBenchmarkType                       =  str_replace(",","",$_POST['CreditorDaysBenchmarkType']) ;
$CreditorDaysGlobalAverage                       =  str_replace(",","",$_POST['CreditorDaysGlobalAverage']) ;
$CreditorDaysBenchmark_Trade                     =  str_replace(",","",$_POST['CreditorDaysBenchmark_Trade']) ;
$CreditorDaysBenchmark_Finance                   =  str_replace(",","",$_POST['CreditorDaysBenchmark_Finance']) ;
$CreditorDaysBenchmark_RealEstate                =  str_replace(",","",$_POST['CreditorDaysBenchmark_RealEstate']) ;
$CreditorDaysBenchmark_Manufacturing             =  str_replace(",","",$_POST['CreditorDaysBenchmark_Manufacturing']) ;
$CreditorDaysBenchmark_Construction              =  str_replace(",","",$_POST['CreditorDaysBenchmark_Construction']) ;
$CreditorDaysBenchmark_Agriculture               =  str_replace(",","",$_POST['CreditorDaysBenchmark_Agriculture']) ;
$CreditorDaysBenchmark_Parastatals                =  str_replace(",","",$_POST['CreditorDaysBenchmark_Parastatals']) ;
$CreditorDaysBenchmark_Transport                 =  str_replace(",","",$_POST['CreditorDaysBenchmark_Transport']) ;
$CreditorDaysBenchmark_Mining                    =  str_replace(",","",$_POST['CreditorDaysBenchmark_Mining']) ;
$CreditorDaysBenchmark_DateUpdated               =  str_replace(",","",$_POST['CreditorDaysBenchmark_DateUpdated']) ;
$CreditorDaysBenchmarkComment                    =  str_replace(",","",$_POST['CreditorDaysBenchmarkComment']) ;
$TurnoverToWCBenchmarkType                       =  str_replace(",","",$_POST['TurnoverToWCBenchmarkType']) ;
$TurnoverToWCGlobalAverage                       =  str_replace(",","",$_POST['TurnoverToWCGlobalAverage']) ;
$TurnoverToWCBenchmark_Trade                     =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Trade']) ;
$TurnoverToWCBenchmark_Finance                   =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Finance']) ;
$TurnoverToWCBenchmark_RealEstate                =  str_replace(",","",$_POST['TurnoverToWCBenchmark_RealEstate']) ;
$TurnoverToWCBenchmark_Manufacturing             =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Manufacturing']) ;
$TurnoverToWCBenchmark_Construction              =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Construction']) ;
$TurnoverToWCBenchmark_Agriculture               =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Agriculture']) ;
$TurnoverToWCBenchmark_Parastatals                =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Parastatals']) ;
$TurnoverToWCBenchmark_Transport                 =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Transport']) ;
$TurnoverToWCBenchmark_Mining                    =  str_replace(",","",$_POST['TurnoverToWCBenchmark_Mining']) ;
$TurnoverToWCBenchmark_DateUpdated               =  str_replace(",","",$_POST['TurnoverToWCBenchmark_DateUpdated']) ;
$TurnoverToWCBenchmarkComment                    =  str_replace(",","",$_POST['TurnoverToWCBenchmarkComment']) ;
$TurnoverGrowthBenchmarkType                     =  str_replace(",","",$_POST['TurnoverGrowthBenchmarkType']) ;
$TurnoverGrowthGlobalAverage                     =  str_replace(",","",$_POST['TurnoverGrowthGlobalAverage']) ;
$TurnoverGrowthBenchmark_Trade                   =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Trade']) ;
$TurnoverGrowthBenchmark_Finance                 =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Finance']) ;
$TurnoverGrowthBenchmark_RealEstate              =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_RealEstate']) ;
$TurnoverGrowthBenchmark_Manufacturing           =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Manufacturing']) ;
$TurnoverGrowthBenchmark_Construction            =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Construction']) ;
$TurnoverGrowthBenchmark_Agriculture             =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Agriculture']) ;
$TurnoverGrowthBenchmark_Parastatals             =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Parastatals']) ;
$TurnoverGrowthBenchmark_Transport               =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Transport']) ;
$TurnoverGrowthBenchmark_Mining                  =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_Mining']) ;
$TurnoverGrowthBenchmark_DateUpdated             =  str_replace(",","",$_POST['TurnoverGrowthBenchmark_DateUpdated']) ;
$TurnoverGrowthBenchmarkComment                  =  str_replace(",","",$_POST['TurnoverGrowthBenchmarkComment']) ;
$GrossProfitMarginBenchmarkType                  =  str_replace(",","",$_POST['GrossProfitMarginBenchmarkType']) ;
$GrossProfitMarginGlobalAverage                  =  str_replace(",","",$_POST['GrossProfitMarginGlobalAverage']) ;
$GrossProfitMarginBenchmark_Trade                =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Trade']) ;
$GrossProfitMarginBenchmark_Finance              =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Finance']) ;
$GrossProfitMarginBenchmark_RealEstate           =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_RealEstate']) ;
$GrossProfitMarginBenchmark_Manufacturing        =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Manufacturing']) ;
$GrossProfitMarginBenchmark_Construction         =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Construction']) ;
$GrossProfitMarginBenchmark_Agriculture          =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Agriculture']) ;
$GrossProfitMarginBenchmark_Parastatals           =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Parastatals']) ;
$GrossProfitMarginBenchmark_Transport            =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Transport']) ;
$GrossProfitMarginBenchmark_Mining               =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_Mining']) ;
$GrossProfitMarginBenchmark_DateUpdated          =  str_replace(",","",$_POST['GrossProfitMarginBenchmark_DateUpdated']) ;
$GrossProfitMarginBenchmarkComment               =  str_replace(",","",$_POST['GrossProfitMarginBenchmarkComment']) ;
$OperatingProfitMarginBenchmarkType              =  str_replace(",","",$_POST['OperatingProfitMarginBenchmarkType']) ;
$OperatingProfitMarginGlobalAverage              =  str_replace(",","",$_POST['OperatingProfitMarginGlobalAverage']) ;
$OperatingProfitMarginBenchmark_Trade            =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Trade']) ;
$OperatingProfitMarginBenchmark_Finance          =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Finance']) ;
$OperatingProfitMarginBenchmark_RealEstate       =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_RealEstate']) ;
$OperatingProfitMarginBenchmark_Manufacturing    =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Manufacturing']) ;
$OperatingProfitMarginBenchmark_Construction     =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Construction']) ;
$OperatingProfitMarginBenchmark_Agriculture      =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Agriculture']) ;
$OperatingProfitMarginBenchmark_Parastatals       =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Parastatals']) ;
$OperatingProfitMarginBenchmark_Transport        =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Transport']) ;
$OperatingProfitMarginBenchmark_Mining           =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_Mining']) ;
$OperatingProfitMarginBenchmark_DateUpdated      =  str_replace(",","",$_POST['OperatingProfitMarginBenchmark_DateUpdated']) ;
$OperatingProfitMarginBenchmarkComment           =  str_replace(",","",$_POST['OperatingProfitMarginBenchmarkComment']) ;
$NetProfitMarginBenchmarkType                    =  str_replace(",","",$_POST['NetProfitMarginBenchmarkType']) ;
$NetProfitMarginGlobalAverage                    =  str_replace(",","",$_POST['NetProfitMarginGlobalAverage']) ;
$NetProfitMarginBenchmark_Trade                  =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Trade']) ;
$NetProfitMarginBenchmark_Finance                =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Finance']) ;
$NetProfitMarginBenchmark_RealEstate             =  str_replace(",","",$_POST['NetProfitMarginBenchmark_RealEstate']) ;
$NetProfitMarginBenchmark_Manufacturing          =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Manufacturing']) ;
$NetProfitMarginBenchmark_Construction           =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Construction']) ;
$NetProfitMarginBenchmark_Agriculture            =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Agriculture']) ;
$NetProfitMarginBenchmark_Parastatals            =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Parastatals']) ;
$NetProfitMarginBenchmark_Transport              =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Transport']) ;
$NetProfitMarginBenchmark_Mining                 =  str_replace(",","",$_POST['NetProfitMarginBenchmark_Mining']) ;
$NetProfitMarginBenchmark_DateUpdated            =  str_replace(",","",$_POST['NetProfitMarginBenchmark_DateUpdated']) ;
$NetProfitMarginBenchmarkComment                 =  str_replace(",","",$_POST['NetProfitMarginBenchmarkComment']) ;
$ROEBenchmarkType                                =  str_replace(",","",$_POST['ROEBenchmarkType']) ;
$ROEGlobalAverage                                =  str_replace(",","",$_POST['ROEGlobalAverage']) ;
$ROEBenchmark_Trade                              =  str_replace(",","",$_POST['ROEBenchmark_Trade']) ;
$ROEBenchmark_Finance                            =  str_replace(",","",$_POST['ROEBenchmark_Finance']) ;
$ROEBenchmark_RealEstate                         =  str_replace(",","",$_POST['ROEBenchmark_RealEstate']) ;
$ROEBenchmark_Manufacturing                      =  str_replace(",","",$_POST['ROEBenchmark_Manufacturing']) ;
$ROEBenchmark_Construction                       =  str_replace(",","",$_POST['ROEBenchmark_Construction']) ;
$ROEBenchmark_Agriculture                        =  str_replace(",","",$_POST['ROEBenchmark_Agriculture']) ;
$ROEBenchmark_Parastatals                        =  str_replace(",","",$_POST['ROEBenchmark_Parastatals']) ;
$ROEBenchmark_Transport                          =  str_replace(",","",$_POST['ROEBenchmark_Transport']) ;
$ROEBenchmark_Mining                             =  str_replace(",","",$_POST['ROEBenchmark_Mining']) ;
$ROEBenchmark_DateUpdated                        =  str_replace(",","",$_POST['ROEBenchmark_DateUpdated']) ;
$ROEBenchmarkComment                             =  str_replace(",","",$_POST['ROEBenchmarkComment']) ;
$ROABenchmarkType                                =  str_replace(",","",$_POST['ROABenchmarkType']) ;
$ROAGlobalAverage                                =  str_replace(",","",$_POST['ROAGlobalAverage']) ;
$ROABenchmark_Trade                              =  str_replace(",","",$_POST['ROABenchmark_Trade']) ;
$ROABenchmark_Finance                            =  str_replace(",","",$_POST['ROABenchmark_Finance']) ;
$ROABenchmark_RealEstate                         =  str_replace(",","",$_POST['ROABenchmark_RealEstate']) ;
$ROABenchmark_Manufacturing                      =  str_replace(",","",$_POST['ROABenchmark_Manufacturing']) ;
$ROABenchmark_Construction                       =  str_replace(",","",$_POST['ROABenchmark_Construction']) ;
$ROABenchmark_Agriculture                        =  str_replace(",","",$_POST['ROABenchmark_Agriculture']) ;
$ROABenchmark_Parastatals                         =  str_replace(",","",$_POST['ROABenchmark_Parastatals']) ;
$ROABenchmark_Transport                          =  str_replace(",","",$_POST['ROABenchmark_Transport']) ;
$ROABenchmark_Mining                             =  str_replace(",","",$_POST['ROABenchmark_Mining']) ;
$ROABenchmark_DateUpdated                        =  str_replace(",","",$_POST['ROABenchmark_DateUpdated']) ;
$ROABenchmarkComment                             =  str_replace(",","",$_POST['ROABenchmarkComment']) ;
$ROIBenchmarkType                                =  str_replace(",","",$_POST['ROIBenchmarkType']) ;
$ROIGlobalAverage                                =  str_replace(",","",$_POST['ROIGlobalAverage']) ;
$ROIBenchmark_Trade                              =  str_replace(",","",$_POST['ROIBenchmark_Trade']) ;
$ROIBenchmark_Finance                            =  str_replace(",","",$_POST['ROIBenchmark_Finance']) ;
$ROIBenchmark_RealEstate                         =  str_replace(",","",$_POST['ROIBenchmark_RealEstate']) ;
$ROIBenchmark_Manufacturing                      =  str_replace(",","",$_POST['ROIBenchmark_Manufacturing']) ;
$ROIBenchmark_Construction                       =  str_replace(",","",$_POST['ROIBenchmark_Construction']) ;
$ROIBenchmark_Agriculture                        =  str_replace(",","",$_POST['ROIBenchmark_Agriculture']) ;
$ROIBenchmark_Parastatals                         =  str_replace(",","",$_POST['ROIBenchmark_Parastatals']) ;
$ROIBenchmark_Transport                          =  str_replace(",","",$_POST['ROIBenchmark_Transport']) ;
$ROIBenchmark_Mining                             =  str_replace(",","",$_POST['ROIBenchmark_Mining']) ;
$ROIBenchmark_DateUpdated                        =  str_replace(",","",$_POST['ROIBenchmark_DateUpdated']) ;
$ROIBenchmarkComment                             =  str_replace(",","",$_POST['ROIBenchmarkComment']) ;
$GearingRatioBenchmarkType                       =  str_replace(",","",$_POST['GearingRatioBenchmarkType']) ;
$GearingRatioGlobalAverage                       =  str_replace(",","",$_POST['GearingRatioGlobalAverage']) ;
$GearingRatioBenchmark_Trade                     =  str_replace(",","",$_POST['GearingRatioBenchmark_Trade']) ;
$GearingRatioBenchmark_Finance                   =  str_replace(",","",$_POST['GearingRatioBenchmark_Finance']) ;
$GearingRatioBenchmark_RealEstate                =  str_replace(",","",$_POST['GearingRatioBenchmark_RealEstate']) ;
$GearingRatioBenchmark_Manufacturing             =  str_replace(",","",$_POST['GearingRatioBenchmark_Manufacturing']) ;
$GearingRatioBenchmark_Construction              =  str_replace(",","",$_POST['GearingRatioBenchmark_Construction']) ;
$GearingRatioBenchmark_Agriculture               =  str_replace(",","",$_POST['GearingRatioBenchmark_Agriculture']) ;
$GearingRatioBenchmark_Parastatals                =  str_replace(",","",$_POST['GearingRatioBenchmark_Parastatals']) ;
$GearingRatioBenchmark_Transport                 =  str_replace(",","",$_POST['GearingRatioBenchmark_Transport']) ;
$GearingRatioBenchmark_Mining                    =  str_replace(",","",$_POST['GearingRatioBenchmark_Mining']) ;
$GearingRatioBenchmark_DateUpdated               =  str_replace(",","",$_POST['GearingRatioBenchmark_DateUpdated']) ;
$GearingRatioBenchmarkComment                    =  str_replace(",","",$_POST['GearingRatioBenchmarkComment']) ;
$LongtermDebtToEquityBenchmarkType               =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmarkType']) ;
$LongtermDebtToEquityGlobalAverage               =  str_replace(",","",$_POST['LongtermDebtToEquityGlobalAverage']) ;
$LongtermDebtToEquityBenchmark_Trade             =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Trade']) ;
$LongtermDebtToEquityBenchmark_Finance           =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Finance']) ;
$LongtermDebtToEquityBenchmark_RealEstate        =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_RealEstate']) ;
$LongtermDebtToEquityBenchmark_Manufacturing     =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Manufacturing']) ;
$LongtermDebtToEquityBenchmark_Construction      =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Construction']) ;
$LongtermDebtToEquityBenchmark_Agriculture       =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Agriculture']) ;
$LongtermDebtToEquityBenchmark_Parastatals        =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Parastatals']) ;
$LongtermDebtToEquityBenchmark_Transport         =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Transport']) ;
$LongtermDebtToEquityBenchmark_Mining            =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_Mining']) ;
$LongtermDebtToEquityBenchmark_DateUpdated       =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmark_DateUpdated']) ;
$LongtermDebtToEquityBenchmarkComment            =  str_replace(",","",$_POST['LongtermDebtToEquityBenchmarkComment']) ;
$DebtToTangibleNetWorthBenchmarkType             =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmarkType']) ;
$DebtToTangibleNetWorthGlobalAverage             =  str_replace(",","",$_POST['DebtToTangibleNetWorthGlobalAverage']) ;
$DebtToTangibleNetWorthBenchmark_Trade           =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Trade']) ;
$DebtToTangibleNetWorthBenchmark_Finance         =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Finance']) ;
$DebtToTangibleNetWorthBenchmark_RealEstate      =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_RealEstate']) ;
$DebtToTangibleNetWorthBenchmark_Manufacturing   =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Manufacturing']) ;
$DebtToTangibleNetWorthBenchmark_Construction    =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Construction']) ;
$DebtToTangibleNetWorthBenchmark_Agriculture     =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Agriculture']) ;
$DebtToTangibleNetWorthBenchmark_Parastatals      =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Parastatals']) ;
$DebtToTangibleNetWorthBenchmark_Transport       =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Transport']) ;
$DebtToTangibleNetWorthBenchmark_Mining          =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_Mining']) ;
$DebtToTangibleNetWorthBenchmark_DateUpdated     =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmark_DateUpdated']) ;
$DebtToTangibleNetWorthBenchmarkComment          =  str_replace(",","",$_POST['DebtToTangibleNetWorthBenchmarkComment']) ;
$EquityToTotalAssetsBenchmarkType                =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmarkType']) ;
$EquityToTotalAssetsGlobalAverage                =  str_replace(",","",$_POST['EquityToTotalAssetsGlobalAverage']) ;
$EquityToTotalAssetsBenchmark_Trade              =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Trade']) ;
$EquityToTotalAssetsBenchmark_Finance            =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Finance']) ;
$EquityToTotalAssetsBenchmark_RealEstate         =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_RealEstate']) ;
$EquityToTotalAssetsBenchmark_Manufacturing      =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Manufacturing']) ;
$EquityToTotalAssetsBenchmark_Construction       =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Construction']) ;
$EquityToTotalAssetsBenchmark_Agriculture        =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Agriculture']) ;
$EquityToTotalAssetsBenchmark_Parastatals         =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Parastatals']) ;
$EquityToTotalAssetsBenchmark_Transport          =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Transport']) ;
$EquityToTotalAssetsBenchmark_Mining             =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_Mining']) ;
$EquityToTotalAssetsBenchmark_DateUpdated        =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmark_DateUpdated']) ;
$EquityToTotalAssetsBenchmarkComment             =  str_replace(",","",$_POST['EquityToTotalAssetsBenchmarkComment']) ;
$SolvencyBenchmarkType                           =  str_replace(",","",$_POST['SolvencyBenchmarkType']) ;
$SolvencyGlobalAverage                           =  str_replace(",","",$_POST['SolvencyGlobalAverage']) ;
$SolvencyBenchmark_Trade                         =  str_replace(",","",$_POST['SolvencyBenchmark_Trade']) ;
$SolvencyBenchmark_Finance                       =  str_replace(",","",$_POST['SolvencyBenchmark_Finance']) ;
$SolvencyBenchmark_RealEstate                    =  str_replace(",","",$_POST['SolvencyBenchmark_RealEstate']) ;
$SolvencyBenchmark_Manufacturing                 =  str_replace(",","",$_POST['SolvencyBenchmark_Manufacturing']) ;
$SolvencyBenchmark_Construction                  =  str_replace(",","",$_POST['SolvencyBenchmark_Construction']) ;
$SolvencyBenchmark_Agriculture                   =  str_replace(",","",$_POST['SolvencyBenchmark_Agriculture']) ;
$SolvencyBenchmark_Parastatals                    =  str_replace(",","",$_POST['SolvencyBenchmark_Parastatals']) ;
$SolvencyBenchmark_Transport                     =  str_replace(",","",$_POST['SolvencyBenchmark_Transport']) ;
$SolvencyBenchmark_Mining                        =  str_replace(",","",$_POST['SolvencyBenchmark_Mining']) ;
$SolvencyBenchmark_DateUpdated                   =  str_replace(",","",$_POST['SolvencyBenchmark_DateUpdated']) ;
$SolvencyBenchmarkComment                        =  str_replace(",","",$_POST['SolvencyBenchmarkComment']) ;
$InterestCoverBenchmarkType                      =  str_replace(",","",$_POST['InterestCoverBenchmarkType']) ;
$InterestCoverGlobalAverage                      =  str_replace(",","",$_POST['InterestCoverGlobalAverage']) ;
$InterestCoverBenchmark_Trade                    =  str_replace(",","",$_POST['InterestCoverBenchmark_Trade']) ;
$InterestCoverBenchmark_Finance                  =  str_replace(",","",$_POST['InterestCoverBenchmark_Finance']) ;
$InterestCoverBenchmark_RealEstate               =  str_replace(",","",$_POST['InterestCoverBenchmark_RealEstate']) ;
$InterestCoverBenchmark_Manufacturing            =  str_replace(",","",$_POST['InterestCoverBenchmark_Manufacturing']) ;
$InterestCoverBenchmark_Construction             =  str_replace(",","",$_POST['InterestCoverBenchmark_Construction']) ;
$InterestCoverBenchmark_Agriculture              =  str_replace(",","",$_POST['InterestCoverBenchmark_Agriculture']) ;
$InterestCoverBenchmark_Parastatals               =  str_replace(",","",$_POST['InterestCoverBenchmark_Parastatals']) ;
$InterestCoverBenchmark_Transport                =  str_replace(",","",$_POST['InterestCoverBenchmark_Transport']) ;
$InterestCoverBenchmark_Mining                   =  str_replace(",","",$_POST['InterestCoverBenchmark_Mining']) ;
$InterestCoverBenchmark_DateUpdated              =  str_replace(",","",$_POST['InterestCoverBenchmark_DateUpdated']) ;
$InterestCoverBenchmarkComment                   =  str_replace(",","",$_POST['InterestCoverBenchmarkComment']) ;
$EBITDAToDebtBenchmarkType                       =  str_replace(",","",$_POST['EBITDAToDebtBenchmarkType']) ;
$EBITDAToDebtGlobalAverage                       =  str_replace(",","",$_POST['EBITDAToDebtGlobalAverage']) ;
$EBITDAToDebtBenchmark_Trade                     =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Trade']) ;
$EBITDAToDebtBenchmark_Finance                   =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Finance']) ;
$EBITDAToDebtBenchmark_RealEstate                =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_RealEstate']) ;
$EBITDAToDebtBenchmark_Manufacturing             =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Manufacturing']) ;
$EBITDAToDebtBenchmark_Construction              =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Construction']) ;
$EBITDAToDebtBenchmark_Agriculture               =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Agriculture']) ;
$EBITDAToDebtBenchmark_Parastatals                =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Parastatals']) ;
$EBITDAToDebtBenchmark_Transport                 =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Transport']) ;
$EBITDAToDebtBenchmark_Mining                    =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_Mining']) ;
$EBITDAToDebtBenchmark_DateUpdated               =  str_replace(",","",$_POST['EBITDAToDebtBenchmark_DateUpdated']) ;
$EBITDAToDebtBenchmarkComment                    =  str_replace(",","",$_POST['EBITDAToDebtBenchmarkComment']) ;
$TotalAssetsTurnoverBenchmarkType                =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmarkType']) ;
$TotalAssetsTurnoverGlobalAverage                =  str_replace(",","",$_POST['TotalAssetsTurnoverGlobalAverage']) ;
$TotalAssetsTurnoverBenchmark_Trade              =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Trade']) ;
$TotalAssetsTurnoverBenchmark_Finance            =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Finance']) ;
$TotalAssetsTurnoverBenchmark_RealEstate         =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_RealEstate']) ;
$TotalAssetsTurnoverBenchmark_Manufacturing      =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Manufacturing']) ;
$TotalAssetsTurnoverBenchmark_Construction       =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Construction']) ;
$TotalAssetsTurnoverBenchmark_Agriculture        =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Agriculture']) ;
$TotalAssetsTurnoverBenchmark_Parastatals         =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Parastatals']) ;
$TotalAssetsTurnoverBenchmark_Transport          =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Transport']) ;
$TotalAssetsTurnoverBenchmark_Mining             =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_Mining']) ;
$TotalAssetsTurnoverBenchmark_DateUpdated        =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmark_DateUpdated']) ;
$TotalAssetsTurnoverBenchmarkComment             =  str_replace(",","",$_POST['TotalAssetsTurnoverBenchmarkComment']) ;
$FixedAssetsTurnoverBenchmarkType               =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmarkType']) ;
$FixedAssetsTurnoverGlobalAverage               =  str_replace(",","",$_POST['FixedAssetsTurnoverGlobalAverage']) ;
$FixedAssetsTurnoverBenchmark_Trade             =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Trade']) ;
$FixedAssetsTurnoverBenchmark_Finance           =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Finance']) ;
$FixedAssetsTurnoverBenchmark_RealEstate        =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_RealEstate']) ;
$FixedAssetsTurnoverBenchmark_Manufacturing     =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Manufacturing']) ;
$FixedAssetsTurnoverBenchmark_Construction      =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Construction']) ;
$FixedAssetsTurnoverBenchmark_Agriculture       =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Agriculture']) ;
$FixedAssetsTurnoverBenchmark_Parastatals       =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Parastatals']) ;
$FixedAssetsTurnoverBenchmark_Transport         =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Transport']) ;
$FixedAssetsTurnoverBenchmark_Mining            =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_Mining']) ;
$FixedAssetsTurnoverBenchmark_DateUpdated       =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmark_DateUpdated']) ;
$FixedAssetsTurnoverBenchmarkComment            =  str_replace(",","",$_POST['FixedAssetsTurnoverBenchmarkComment']) ;



// Save Data to Database		 

	 
	     $pass= "";
         $host="localhost"; 
         $user="root"; 
         $db_name="corporatescoring"; 
                   // Connect to server and select databse.
                   //$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
                   //echo $ip;


        $connect=mysqli_connect($host,$user,$pass,$db_name); 
    
        if (!$connect) 
	      {
             mysqli_close($connect); 
             echo "Cannot connect to the database! Please Check your username and password."; 
          }
        else 
		  {
             echo 'Successful Connected';
			 
			// mysqli_select_db( $connect,$db_name)  
            // or die("Couldn't open $dbase: ".mysqli_error());

          }

/**
        $insert_query="INSERT INTO financials 
		SET
		    company_reg_no = '1',
			update_type = 'application',
			username = 'Edward',
			report_name = 'BS',
			line_desc = 'OpeningNBVYear1',
			reporting_year = '$OpeningNBVYear1',
				amount = '$OpeningNBVYear1',
			comment = '$OtherNonCurrentAssetsComments'
		    ";
  **/
         
		$username = $_POST['username'];
		
		//$CurrentRatioBenchmark_DateUpdated = NULL; 
		 //========================UPDATE THE PROGRESS TRACKER - Industry Benchmarks Data Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Industry Benchmarks' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
	         
		 $insert_query  =  "REPLACE INTO industry_ratio_benchmarks
         (ratio , units , bench_mark_type, global_average , trade, finance_and_business , real_estate , manufacturing ,  
          construction  , agriculture ,parastatals, transport_and_communications, mining , date_updated , username,data_source )
         
		 VALUES
         ('CurrentRatio','times','$CurrentRatioBenchmarkType',
         '$CurrentRatioGlobalAverage',
         '$CurrentRatioBenchmark_Trade',
         '$CurrentRatioBenchmark_Finance',
         '$CurrentRatioBenchmark_RealEstate',
         '$CurrentRatioBenchmark_Manufacturing',
         '$CurrentRatioBenchmark_Construction',
         '$CurrentRatioBenchmark_Agriculture',
         '$CurrentRatioBenchmark_Parastatals',
         '$CurrentRatioBenchmark_Transport',
         '$CurrentRatioBenchmark_Mining',
         '$CurrentRatioBenchmark_DateUpdated',
         '$username','$CurrentRatioBenchmarkComment'),
         ('QuickRatio','times','$QuickRatioBenchmarkType',
         '$QuickRatioGlobalAverage',
         '$QuickRatioBenchmark_Trade',
         '$QuickRatioBenchmark_Finance',
         '$QuickRatioBenchmark_RealEstate',
         '$QuickRatioBenchmark_Manufacturing',
         '$QuickRatioBenchmark_Construction',
         '$QuickRatioBenchmark_Agriculture',
         '$QuickRatioBenchmark_Parastatals',
         '$QuickRatioBenchmark_Transport',
         '$QuickRatioBenchmark_Mining',
         '$QuickRatioBenchmark_DateUpdated',
         '$username','$QuickRatioBenchmarkComment'),
         ('DebtorDays','Days','$DebtorDaysBenchmarkType',
         '$DebtorDaysGlobalAverage',
         '$DebtorDaysBenchmark_Trade',
         '$DebtorDaysBenchmark_Finance',
         '$DebtorDaysBenchmark_RealEstate',
         '$DebtorDaysBenchmark_Manufacturing',
         '$DebtorDaysBenchmark_Construction',
         '$DebtorDaysBenchmark_Agriculture',
         '$DebtorDaysBenchmark_Parastatals',
         '$DebtorDaysBenchmark_Transport',
         '$DebtorDaysBenchmark_Mining',
         '$DebtorDaysBenchmark_DateUpdated',
         '$username','$DebtorDaysBenchmarkComment'),
         ('CreditorDays','Days','$CreditorDaysBenchmarkType',
         '$CreditorDaysGlobalAverage',
         '$CreditorDaysBenchmark_Trade',
         '$CreditorDaysBenchmark_Finance',
         '$CreditorDaysBenchmark_RealEstate',
         '$CreditorDaysBenchmark_Manufacturing',
         '$CreditorDaysBenchmark_Construction',
         '$CreditorDaysBenchmark_Agriculture',
         '$CreditorDaysBenchmark_Parastatals',
         '$CreditorDaysBenchmark_Transport',
         '$CreditorDaysBenchmark_Mining',
         '$CreditorDaysBenchmark_DateUpdated',
         '$username','$CreditorDaysBenchmarkComment'),
         ('Turnover/WorkingCapital','%','$TurnoverToWCBenchmarkType',
         '$TurnoverToWCGlobalAverage',
         '$TurnoverToWCBenchmark_Trade',
         '$TurnoverToWCBenchmark_Finance',
         '$TurnoverToWCBenchmark_RealEstate',
         '$TurnoverToWCBenchmark_Manufacturing',
         '$TurnoverToWCBenchmark_Construction',
         '$TurnoverToWCBenchmark_Agriculture',
         '$TurnoverToWCBenchmark_Parastatals',
         '$TurnoverToWCBenchmark_Transport',
         '$TurnoverToWCBenchmark_Mining',
         '$TurnoverToWCBenchmark_DateUpdated',
         '$username','$TurnoverToWCBenchmarkComment'),
         ('TurnoverGrowth','%','$TurnoverGrowthBenchmarkType',
         '$TurnoverGrowthGlobalAverage',
         '$TurnoverGrowthBenchmark_Trade',
         '$TurnoverGrowthBenchmark_Finance',
         '$TurnoverGrowthBenchmark_RealEstate',
         '$TurnoverGrowthBenchmark_Manufacturing',
         '$TurnoverGrowthBenchmark_Construction',
         '$TurnoverGrowthBenchmark_Agriculture',
         '$TurnoverGrowthBenchmark_Parastatals',
         '$TurnoverGrowthBenchmark_Transport',
         '$TurnoverGrowthBenchmark_Mining',
         '$TurnoverGrowthBenchmark_DateUpdated',
         '$username','$TurnoverGrowthBenchmarkComment'),
         ('GrossProfit%','%','$GrossProfitMarginBenchmarkType',
         '$GrossProfitMarginGlobalAverage',
         '$GrossProfitMarginBenchmark_Trade',
         '$GrossProfitMarginBenchmark_Finance',
         '$GrossProfitMarginBenchmark_RealEstate',
         '$GrossProfitMarginBenchmark_Manufacturing',
         '$GrossProfitMarginBenchmark_Construction',
         '$GrossProfitMarginBenchmark_Agriculture',
         '$GrossProfitMarginBenchmark_Parastatals',
         '$GrossProfitMarginBenchmark_Transport',
         '$GrossProfitMarginBenchmark_Mining',
         '$GrossProfitMarginBenchmark_DateUpdated',
         '$username','$GrossProfitMarginBenchmarkComment'),
         ('OperatingProfitMargin','%','$OperatingProfitMarginBenchmarkType',
         '$OperatingProfitMarginGlobalAverage',
         '$OperatingProfitMarginBenchmark_Trade',
         '$OperatingProfitMarginBenchmark_Finance',
         '$OperatingProfitMarginBenchmark_RealEstate',
         '$OperatingProfitMarginBenchmark_Manufacturing',
         '$OperatingProfitMarginBenchmark_Construction',
         '$OperatingProfitMarginBenchmark_Agriculture',
         '$OperatingProfitMarginBenchmark_Parastatals',
         '$OperatingProfitMarginBenchmark_Transport',
         '$OperatingProfitMarginBenchmark_Mining',
         '$OperatingProfitMarginBenchmark_DateUpdated',
         '$username','$OperatingProfitMarginBenchmarkComment'),
         ('NetProfitMargin','%','$NetProfitMarginBenchmarkType',
         '$NetProfitMarginGlobalAverage',
         '$NetProfitMarginBenchmark_Trade',
         '$NetProfitMarginBenchmark_Finance',
         '$NetProfitMarginBenchmark_RealEstate',
         '$NetProfitMarginBenchmark_Manufacturing',
         '$NetProfitMarginBenchmark_Construction',
         '$NetProfitMarginBenchmark_Agriculture',
         '$NetProfitMarginBenchmark_Parastatals',
         '$NetProfitMarginBenchmark_Transport',
         '$NetProfitMarginBenchmark_Mining',
         '$NetProfitMarginBenchmark_DateUpdated',
         '$username','$NetProfitMarginBenchmarkComment'),
         ('ReturnOnEquity(ROE)','%','$ROEBenchmarkType',
         '$ROEGlobalAverage',
         '$ROEBenchmark_Trade',
         '$ROEBenchmark_Finance',
         '$ROEBenchmark_RealEstate',
         '$ROEBenchmark_Manufacturing',
         '$ROEBenchmark_Construction',
         '$ROEBenchmark_Agriculture',
         '$ROEBenchmark_Parastatals',
         '$ROEBenchmark_Transport',
         '$ROEBenchmark_Mining',
         '$ROEBenchmark_DateUpdated',
         '$username','$ROEBenchmarkComment'),
         ('ReturnOnAssets(ROA)','%','$ROABenchmarkType',
         '$ROAGlobalAverage',
         '$ROABenchmark_Trade',
         '$ROABenchmark_Finance',
         '$ROABenchmark_RealEstate',
         '$ROABenchmark_Manufacturing',
         '$ROABenchmark_Construction',
         '$ROABenchmark_Agriculture',
         '$ROABenchmark_Parastatals',
         '$ROABenchmark_Transport',
         '$ROABenchmark_Mining',
         '$ROABenchmark_DateUpdated',
         '$username','$ROABenchmarkComment'),
         ('ReturnOnInvestments(ROI)','%','$ROIBenchmarkType',
         '$ROIGlobalAverage',
         '$ROIBenchmark_Trade',
         '$ROIBenchmark_Finance',
         '$ROIBenchmark_RealEstate',
         '$ROIBenchmark_Manufacturing',
         '$ROIBenchmark_Construction',
         '$ROIBenchmark_Agriculture',
         '$ROIBenchmark_Parastatals',
         '$ROIBenchmark_Transport',
         '$ROIBenchmark_Mining',
         '$ROIBenchmark_DateUpdated',
         '$username','$ROIBenchmarkComment'),
         ('GearingRatio','%','$GearingRatioBenchmarkType',
         '$GearingRatioGlobalAverage',
         '$GearingRatioBenchmark_Trade',
         '$GearingRatioBenchmark_Finance',
         '$GearingRatioBenchmark_RealEstate',
         '$GearingRatioBenchmark_Manufacturing',
         '$GearingRatioBenchmark_Construction',
         '$GearingRatioBenchmark_Agriculture',
         '$GearingRatioBenchmark_Parastatals',
         '$GearingRatioBenchmark_Transport',
         '$GearingRatioBenchmark_Mining',
         '$GearingRatioBenchmark_DateUpdated',
         '$username','$GearingRatioBenchmarkComment'),
         ('Long-termDebt/Equity','%','$LongtermDebtToEquityBenchmarkType',
         '$LongtermDebtToEquityGlobalAverage',
         '$LongtermDebtToEquityBenchmark_Trade',
         '$LongtermDebtToEquityBenchmark_Finance',
         '$LongtermDebtToEquityBenchmark_RealEstate',
         '$LongtermDebtToEquityBenchmark_Manufacturing',
         '$LongtermDebtToEquityBenchmark_Construction',
         '$LongtermDebtToEquityBenchmark_Agriculture',
         '$LongtermDebtToEquityBenchmark_Parastatals',
         '$LongtermDebtToEquityBenchmark_Transport',
         '$LongtermDebtToEquityBenchmark_Mining',
         '$LongtermDebtToEquityBenchmark_DateUpdated',
         '$username','$LongtermDebtToEquityBenchmarkComment'),
         ('TangibleNetWorth','%','$DebtToTangibleNetWorthBenchmarkType',
         '$DebtToTangibleNetWorthGlobalAverage',
         '$DebtToTangibleNetWorthBenchmark_Trade',
         '$DebtToTangibleNetWorthBenchmark_Finance',
         '$DebtToTangibleNetWorthBenchmark_RealEstate',
         '$DebtToTangibleNetWorthBenchmark_Manufacturing',
         '$DebtToTangibleNetWorthBenchmark_Construction',
         '$DebtToTangibleNetWorthBenchmark_Agriculture',
         '$DebtToTangibleNetWorthBenchmark_Parastatals',
         '$DebtToTangibleNetWorthBenchmark_Transport',
         '$DebtToTangibleNetWorthBenchmark_Mining',
         '$DebtToTangibleNetWorthBenchmark_DateUpdated',
         '$username','$DebtToTangibleNetWorthBenchmarkComment'),
         ('Equity/TotalAssets','%','$EquityToTotalAssetsBenchmarkType',
         '$EquityToTotalAssetsGlobalAverage',
         '$EquityToTotalAssetsBenchmark_Trade',
         '$EquityToTotalAssetsBenchmark_Finance',
         '$EquityToTotalAssetsBenchmark_RealEstate',
         '$EquityToTotalAssetsBenchmark_Manufacturing',
         '$EquityToTotalAssetsBenchmark_Construction',
         '$EquityToTotalAssetsBenchmark_Agriculture',
         '$EquityToTotalAssetsBenchmark_Parastatals',
         '$EquityToTotalAssetsBenchmark_Transport',
         '$EquityToTotalAssetsBenchmark_Mining',
         '$EquityToTotalAssetsBenchmark_DateUpdated',
         '$username','$EquityToTotalAssetsBenchmarkComment'),
         ('Solvency','%','$SolvencyBenchmarkType',
         '$SolvencyGlobalAverage',
         '$SolvencyBenchmark_Trade',
         '$SolvencyBenchmark_Finance',
         '$SolvencyBenchmark_RealEstate',
         '$SolvencyBenchmark_Manufacturing',
         '$SolvencyBenchmark_Construction',
         '$SolvencyBenchmark_Agriculture',
         '$SolvencyBenchmark_Parastatals',
         '$SolvencyBenchmark_Transport',
         '$SolvencyBenchmark_Mining',
         '$SolvencyBenchmark_DateUpdated',
         '$username','$SolvencyBenchmarkComment'),
         ('InterestCover','times','$InterestCoverBenchmarkType',
         '$InterestCoverGlobalAverage',
         '$InterestCoverBenchmark_Trade',
         '$InterestCoverBenchmark_Finance',
         '$InterestCoverBenchmark_RealEstate',
         '$InterestCoverBenchmark_Manufacturing',
         '$InterestCoverBenchmark_Construction',
         '$InterestCoverBenchmark_Agriculture',
         '$InterestCoverBenchmark_Parastatals',
         '$InterestCoverBenchmark_Transport',
         '$InterestCoverBenchmark_Mining',
         '$InterestCoverBenchmark_DateUpdated',
         '$username','$InterestCoverBenchmarkComment'),
         ('EBITDA/GrossInterestDebts','%','$EBITDAToDebtBenchmarkType',
         '$EBITDAToDebtGlobalAverage',
         '$EBITDAToDebtBenchmark_Trade',
         '$EBITDAToDebtBenchmark_Finance',
         '$EBITDAToDebtBenchmark_RealEstate',
         '$EBITDAToDebtBenchmark_Manufacturing',
         '$EBITDAToDebtBenchmark_Construction',
         '$EBITDAToDebtBenchmark_Agriculture',
         '$EBITDAToDebtBenchmark_Parastatals',
         '$EBITDAToDebtBenchmark_Transport',
         '$EBITDAToDebtBenchmark_Mining',
         '$EBITDAToDebtBenchmark_DateUpdated',
         '$username','$EBITDAToDebtBenchmarkComment'),
         ('TotalAssets/Turnover','%','$TotalAssetsTurnoverBenchmarkType',
         '$TotalAssetsTurnoverGlobalAverage',
         '$TotalAssetsTurnoverBenchmark_Trade',
         '$TotalAssetsTurnoverBenchmark_Finance',
         '$TotalAssetsTurnoverBenchmark_RealEstate',
         '$TotalAssetsTurnoverBenchmark_Manufacturing',
         '$TotalAssetsTurnoverBenchmark_Construction',
         '$TotalAssetsTurnoverBenchmark_Agriculture',
         '$TotalAssetsTurnoverBenchmark_Parastatals',
         '$TotalAssetsTurnoverBenchmark_Transport',
         '$TotalAssetsTurnoverBenchmark_Mining',
         '$TotalAssetsTurnoverBenchmark_DateUpdated',
         '$username','$TotalAssetsTurnoverBenchmarkComment'),
         ('FixedAssetsTurnover','%','$FixedAssetsTurnoverBenchmarkType',
         '$FixedAssetsTurnoverGlobalAverage',
         '$FixedAssetsTurnoverBenchmark_Trade',
         '$FixedAssetsTurnoverBenchmark_Finance',
         '$FixedAssetsTurnoverBenchmark_RealEstate',
         '$FixedAssetsTurnoverBenchmark_Manufacturing',
         '$FixedAssetsTurnoverBenchmark_Construction',
         '$FixedAssetsTurnoverBenchmark_Agriculture',
         '$FixedAssetsTurnoverBenchmark_Parastatals',
         '$FixedAssetsTurnoverBenchmark_Transport',
         '$FixedAssetsTurnoverBenchmark_Mining',
         '$FixedAssetsTurnoverBenchmark_DateUpdated',
         '$username','$FixedAssetsTurnoverBenchmarkComment')";


	
	
	$resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
 		    

		  header("Location:CorporateAddMenu.php");
          exit;		     
		  echo 'Data successfully saved';
	      }
 ?>
</body>

</html>