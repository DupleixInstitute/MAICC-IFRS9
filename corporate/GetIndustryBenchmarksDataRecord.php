<?php
  //Allocating the SQL record into the specific PHP holding variable for each reading loop PASS================================================
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmarkType                       = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioGlobalAverage                       = $row["global_average"              ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Trade                     = $row["trade"                       ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Finance                   = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_RealEstate                = $row["real_estate"                 ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Manufacturing             = $row["manufacturing"               ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Construction              = $row["construction"                ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Agriculture               = $row["agriculture"                 ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Parastatals               = $row["parastatals"                 ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Transport                 = $row["transport_and_communications"];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_Mining                    = $row["mining"                      ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmark_DateUpdated               = $row["date_updated"                ];}
  if($row["ratio"] ==  "CurrentRatio"             )  { $CurrentRatioBenchmarkComment                    = $row["data_source"                 ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmarkType                         = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioGlobalAverage                         = $row["global_average"              ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Trade                       = $row["trade"                       ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Finance                     = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_RealEstate                  = $row["real_estate"                 ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Manufacturing               = $row["manufacturing"               ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Construction                = $row["construction"                ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Agriculture                 = $row["agriculture"                 ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Parastatals                 = $row["parastatals"                 ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Transport                   = $row["transport_and_communications"];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_Mining                      = $row["mining"                      ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmark_DateUpdated                 = $row["date_updated"                ];}
  if($row["ratio"] ==  "QuickRatio"               )  { $QuickRatioBenchmarkComment                      = $row["data_source"                 ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmarkType                         = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysGlobalAverage                         = $row["global_average"              ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Trade                       = $row["trade"                       ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Finance                     = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_RealEstate                  = $row["real_estate"                 ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Manufacturing               = $row["manufacturing"               ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Construction                = $row["construction"                ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Agriculture                 = $row["agriculture"                 ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Parastatals                 = $row["parastatals"                 ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Transport                   = $row["transport_and_communications"];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_Mining                      = $row["mining"                      ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmark_DateUpdated                 = $row["date_updated"                ];}
  if($row["ratio"] ==  "DebtorDays"               )  { $DebtorDaysBenchmarkComment                      = $row["data_source"                 ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmarkType                       = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysGlobalAverage                       = $row["global_average"              ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Trade                     = $row["trade"                       ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Finance                   = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_RealEstate                = $row["real_estate"                 ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Manufacturing             = $row["manufacturing"               ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Construction              = $row["construction"                ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Agriculture               = $row["agriculture"                 ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Parastatals               = $row["parastatals"                 ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Transport                 = $row["transport_and_communications"];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_Mining                    = $row["mining"                      ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmark_DateUpdated               = $row["date_updated"                ];}
  if($row["ratio"] ==  "CreditorDays"             )  { $CreditorDaysBenchmarkComment                    = $row["data_source"                 ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmarkType                       = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCGlobalAverage                       = $row["global_average"              ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Trade                     = $row["trade"                       ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Finance                   = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_RealEstate                = $row["real_estate"                 ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Manufacturing             = $row["manufacturing"               ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Construction              = $row["construction"                ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Agriculture               = $row["agriculture"                 ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Parastatals               = $row["parastatals"                 ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Transport                 = $row["transport_and_communications"];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_Mining                    = $row["mining"                      ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmark_DateUpdated               = $row["date_updated"                ];}
  if($row["ratio"] ==  "Turnover/WorkingCapital"  )  { $TurnoverToWCBenchmarkComment                    = $row["data_source"                 ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )  	 { $TurnoverGrowthBenchmarkType                     = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthGlobalAverage                     = $row["global_average"              ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Trade                   = $row["trade"                       ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Finance                 = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_RealEstate              = $row["real_estate"                 ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Manufacturing           = $row["manufacturing"               ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Construction            = $row["construction"                ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Agriculture             = $row["agriculture"                 ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Parastatals             = $row["parastatals"                 ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Transport               = $row["transport_and_communications"];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_Mining                  = $row["mining"                      ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmark_DateUpdated             = $row["date_updated"                ];}
  if($row["ratio"] ==  "TurnoverGrowth"      )       { $TurnoverGrowthBenchmarkComment                  = $row["data_source"                 ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmarkType                  = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginGlobalAverage                  = $row["global_average"              ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Trade                = $row["trade"                       ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Finance              = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_RealEstate           = $row["real_estate"                 ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Manufacturing        = $row["manufacturing"               ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Construction         = $row["construction"                ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Agriculture          = $row["agriculture"                 ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Parastatals          = $row["parastatals"                 ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Transport            = $row["transport_and_communications"];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_Mining               = $row["mining"                      ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmark_DateUpdated          = $row["date_updated"                ];}
  if($row["ratio"] ==  "GrossProfit%"             )  { $GrossProfitMarginBenchmarkComment               = $row["data_source"                 ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmarkType              = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginGlobalAverage              = $row["global_average"              ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Trade            = $row["trade"                       ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Finance          = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_RealEstate       = $row["real_estate"                 ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Manufacturing    = $row["manufacturing"               ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Construction     = $row["construction"                ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Agriculture      = $row["agriculture"                 ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Parastatals      = $row["parastatals"                 ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Transport        = $row["transport_and_communications"];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_Mining           = $row["mining"                      ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmark_DateUpdated      = $row["date_updated"                ];}
  if($row["ratio"] ==  "OperatingProfitMargin"    )  { $OperatingProfitMarginBenchmarkComment           = $row["data_source"                 ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmarkType                    = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginGlobalAverage                    = $row["global_average"              ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Trade                  = $row["trade"                       ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Finance                = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_RealEstate             = $row["real_estate"                 ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Manufacturing          = $row["manufacturing"               ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Construction           = $row["construction"                ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Agriculture            = $row["agriculture"                 ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Parastatals            = $row["parastatals"                 ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Transport              = $row["transport_and_communications"];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_Mining                 = $row["mining"                      ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmark_DateUpdated            = $row["date_updated"                ];}
  if($row["ratio"] ==  "NetProfitMargin"          )  { $NetProfitMarginBenchmarkComment                 = $row["data_source"                 ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmarkType                                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEGlobalAverage                                = $row["global_average"              ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Trade                              = $row["trade"                       ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Finance                            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_RealEstate                         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Manufacturing                      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Construction                       = $row["construction"                ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Agriculture                        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Parastatals                        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Transport                          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_Mining                             = $row["mining"                      ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmark_DateUpdated                        = $row["date_updated"                ];}
  if($row["ratio"] ==  "ReturnOnEquity(ROE)"      )  { $ROEBenchmarkComment                             = $row["data_source"                 ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmarkType                                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROAGlobalAverage                                = $row["global_average"              ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Trade                              = $row["trade"                       ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Finance                            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_RealEstate                         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Manufacturing                      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Construction                       = $row["construction"                ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Agriculture                        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Parastatals                        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Transport                          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_Mining                             = $row["mining"                      ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmark_DateUpdated                        = $row["date_updated"                ];}
  if($row["ratio"] ==  "ReturnOnAssets(ROA)"      )  { $ROABenchmarkComment                             = $row["data_source"                 ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmarkType                                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIGlobalAverage                                = $row["global_average"              ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Trade                              = $row["trade"                       ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Finance                            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_RealEstate                         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Manufacturing                      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Construction                       = $row["construction"                ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Agriculture                        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Parastatals                        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Transport                          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_Mining                             = $row["mining"                      ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmark_DateUpdated                        = $row["date_updated"                ];}
  if($row["ratio"] ==  "ReturnOnInvestments(ROI)" )  { $ROIBenchmarkComment                             = $row["data_source"                 ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmarkType                       = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioGlobalAverage                       = $row["global_average"              ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Trade                     = $row["trade"                       ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Finance                   = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_RealEstate                = $row["real_estate"                 ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Manufacturing             = $row["manufacturing"               ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Construction              = $row["construction"                ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Agriculture               = $row["agriculture"                 ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Parastatals               = $row["parastatals"                 ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Transport                 = $row["transport_and_communications"];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_Mining                    = $row["mining"                      ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmark_DateUpdated               = $row["date_updated"                ];}
  if($row["ratio"] ==  "GearingRatio"             )  { $GearingRatioBenchmarkComment                    = $row["data_source"                 ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmarkType               = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityGlobalAverage               = $row["global_average"              ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Trade             = $row["trade"                       ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Finance           = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_RealEstate        = $row["real_estate"                 ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Manufacturing     = $row["manufacturing"               ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Construction      = $row["construction"                ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Agriculture       = $row["agriculture"                 ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Parastatals       = $row["parastatals"                 ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Transport         = $row["transport_and_communications"];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_Mining            = $row["mining"                      ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmark_DateUpdated       = $row["date_updated"                ];}
  if($row["ratio"] ==  "Long-termDebt/Equity"     )  { $LongtermDebtToEquityBenchmarkComment            = $row["data_source"                 ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmarkType             = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthGlobalAverage             = $row["global_average"              ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Trade           = $row["trade"                       ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Finance         = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_RealEstate      = $row["real_estate"                 ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Manufacturing   = $row["manufacturing"               ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Construction    = $row["construction"                ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Agriculture     = $row["agriculture"                 ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Parastatals     = $row["parastatals"                 ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Transport       = $row["transport_and_communications"];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_Mining          = $row["mining"                      ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmark_DateUpdated     = $row["date_updated"                ];}
  if($row["ratio"] ==  "TangibleNetWorth"         )  { $DebtToTangibleNetWorthBenchmarkComment          = $row["data_source"                 ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmarkType                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsGlobalAverage                = $row["global_average"              ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Trade              = $row["trade"                       ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Finance            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_RealEstate         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Manufacturing      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Construction       = $row["construction"                ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Agriculture        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Parastatals        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Transport          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_Mining             = $row["mining"                      ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmark_DateUpdated        = $row["date_updated"                ];}
  if($row["ratio"] ==  "Equity/TotalAssets"       )  { $EquityToTotalAssetsBenchmarkComment             = $row["data_source"                 ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmarkType                           = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyGlobalAverage                           = $row["global_average"              ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Trade                         = $row["trade"                       ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Finance                       = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_RealEstate                    = $row["real_estate"                 ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Manufacturing                 = $row["manufacturing"               ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Construction                  = $row["construction"                ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Agriculture                   = $row["agriculture"                 ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Parastatals                   = $row["parastatals"                 ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Transport                     = $row["transport_and_communications"];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_Mining                        = $row["mining"                      ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmark_DateUpdated                   = $row["date_updated"                ];}
  if($row["ratio"] ==  "Solvency"                 )  { $SolvencyBenchmarkComment                        = $row["data_source"                 ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmarkType                      = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverGlobalAverage                      = $row["global_average"              ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Trade                    = $row["trade"                       ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Finance                  = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_RealEstate               = $row["real_estate"                 ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Manufacturing            = $row["manufacturing"               ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Construction             = $row["construction"                ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Agriculture              = $row["agriculture"                 ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Parastatals              = $row["parastatals"                 ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Transport                = $row["transport_and_communications"];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_Mining                   = $row["mining"                      ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmark_DateUpdated              = $row["date_updated"                ];}
  if($row["ratio"] ==  "InterestCover"            )  { $InterestCoverBenchmarkComment                   = $row["data_source"                 ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmarkType                       = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtGlobalAverage                       = $row["global_average"              ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Trade                     = $row["trade"                       ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Finance                   = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_RealEstate                = $row["real_estate"                 ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Manufacturing             = $row["manufacturing"               ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Construction              = $row["construction"                ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Agriculture               = $row["agriculture"                 ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Parastatals               = $row["parastatals"                 ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Transport                 = $row["transport_and_communications"];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_Mining                    = $row["mining"                      ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmark_DateUpdated               = $row["date_updated"                ];}
  if($row["ratio"] ==  "EBITDA/GrossInterestDebts")  { $EBITDAToDebtBenchmarkComment                    = $row["data_source"                 ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmarkType                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverGlobalAverage                = $row["global_average"              ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Trade              = $row["trade"                       ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Finance            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_RealEstate         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Manufacturing      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Construction       = $row["construction"                ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Agriculture        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Parastatals        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Transport          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_Mining             = $row["mining"                      ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmark_DateUpdated        = $row["date_updated"                ];}
  if($row["ratio"] ==  "TotalAssets/Turnover"     )  { $TotalAssetsTurnoverBenchmarkComment             = $row["data_source"                 ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmarkType                = $row["bench_mark_type"             ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverGlobalAverage                = $row["global_average"              ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Trade              = $row["trade"                       ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Finance            = $row["finance_and_business"        ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_RealEstate         = $row["real_estate"                 ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Manufacturing      = $row["manufacturing"               ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Construction       = $row["construction"                ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Agriculture        = $row["agriculture"                 ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Parastatals        = $row["parastatals"                 ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Transport          = $row["transport_and_communications"];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_Mining             = $row["mining"                      ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmark_DateUpdated        = $row["date_updated"                ];}
  if($row["ratio"] ==  "FixedAssetsTurnover"      )  { $FixedAssetsTurnoverBenchmarkComment             = $row["data_source"                 ];}


    
     
?>
