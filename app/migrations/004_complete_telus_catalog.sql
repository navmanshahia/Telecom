-- 004_complete_telus_catalog.sql
-- Additional customer-facing TELUS offers transcribed from the supplied West Coaches Picks.
-- Do not expose internal promo codes/job-aid links.

-- Copper Internet
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,speed_data,fine_print,status)
SELECT p.id,'internet','TELUS Copper Internet up to 50 Mbps','TELUS copper internet offer.',45,95,'Up to 50 Mbps','Final price shown on supplied sheet after applicable promotional/closing/M&H/PAP discounts. Address and eligibility verification required.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='TELUS Copper Internet up to 50 Mbps');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,speed_data,fine_print,status)
SELECT p.id,'internet','TELUS Copper Internet Plus up to 150 Mbps','TELUS copper Internet Plus offer.',50,110,'Up to 150 Mbps','Final price shown on supplied sheet after applicable discounts. Address and eligibility verification required.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='TELUS Copper Internet Plus up to 150 Mbps');

-- Wireless Home Internet
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'internet','Wireless Home Internet 25','TELUS wireless home internet.',55,'25 Mbps','Supplied sheet shows $55 final price and $0 professional installation; address/technology eligibility applies.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Wireless Home Internet 25');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'internet','Wireless Home Internet 50','TELUS wireless home internet.',55,'50 Mbps','Supplied sheet shows $55 final price and $0 professional installation; address/technology eligibility applies.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Wireless Home Internet 50');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'internet','Wireless Home Internet 100','TELUS wireless home internet.',55,'100 Mbps','Supplied sheet shows $55 final price and $0 professional installation; address/technology eligibility applies.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Wireless Home Internet 100');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'internet','Wireless Home Internet 200','TELUS wireless home internet.',55,'200 Mbps','Supplied sheet shows $55 final price and $0 professional installation; address/technology eligibility applies.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Wireless Home Internet 200');

-- Shock-Free TV variants for eligible PureFibre 1G/1.5G/3G customers
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Shock-Free Core TV + 1 Theme Pack & Sports','Special TV bundle for eligible PureFibre 1 Gig, 1.5 Gig or 3 Gig customers.',32,'Requires eligible qualifying internet bundle. Verify current eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Shock-Free Core TV + 1 Theme Pack & Sports');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Shock-Free Core TV + 1 Theme Pack & 1 Premium','Special TV bundle for eligible PureFibre 1 Gig, 1.5 Gig or 3 Gig customers.',42,'Requires eligible qualifying internet bundle. Verify current eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Shock-Free Core TV + 1 Theme Pack & 1 Premium');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Shock-Free Core TV + 1 Theme Pack & 2 Premium','Special TV bundle for eligible PureFibre 1 Gig, 1.5 Gig or 3 Gig customers.',47,'Requires eligible qualifying internet bundle. Verify current eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Shock-Free Core TV + 1 Theme Pack & 2 Premium');

-- Security 1.0
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,fine_print,status)
SELECT p.id,'security','Secure + Video','TELUS security package with control panel, sensors, SmartHome app and eligible camera/equipment.',68,36,'3-year term. Equipment and installation details must be confirmed. BC processing restriction noted on supplied sheet.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Secure + Video');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,fine_print,status)
SELECT p.id,'security','Control + Video','TELUS security package with control panel, sensors, SmartHome app, camera and eligible automation devices.',75,36,'3-year term. Equipment and installation details must be confirmed. BC processing restriction noted on supplied sheet.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Control + Video');

-- Streaming
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'streaming','Stream+ Basic','Netflix Standard, Disney+ Basic with ads and Amazon Prime.',23,'Month-to-month/no contract as shown on supplied sheet. Verify current included services.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Stream+ Basic');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'streaming','Stream+ Premium','TELUS Stream+ Premium entertainment bundle.',43,'Month-to-month/no contract as shown on supplied sheet. Verify current included services.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Stream+ Premium');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'streaming','Stream+ Basic + Apple TV','Stream+ Basic bundled with Apple TV.',36.50,'Verify current bundle inclusions and eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Stream+ Basic + Apple TV');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'streaming','Stream+ Premium + Apple TV','Stream+ Premium bundled with Apple TV.',56.50,'Verify current bundle inclusions and eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Stream+ Premium + Apple TV');

-- Device financing / Bring-It-Back offers from supplied sheet
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Samsung Galaxy Z Flip8 256GB','Monthly device financing shown with Bring-It-Back.',25.17,50.21,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Samsung Galaxy Z Flip8 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Samsung Galaxy S26 256GB','Monthly device financing shown with Bring-It-Back.',14.96,35.00,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Samsung Galaxy S26 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Google Pixel 11 256GB','Monthly device financing shown with Bring-It-Back.',36.21,53.92,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Google Pixel 11 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Google Pixel 10 Pro 128GB','Monthly device financing shown with Bring-It-Back.',0,12.08,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Google Pixel 10 Pro 128GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Apple iPhone 18 Pro 256GB','Monthly device financing shown with Bring-It-Back.',37.50,74.96,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Apple iPhone 18 Pro 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Apple iPhone 17 Pro 256GB','Monthly device financing shown with Bring-It-Back.',17.58,68.50,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Apple iPhone 17 Pro 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Apple iPhone 17 256GB','Monthly device financing shown with Bring-It-Back.',27.83,55.63,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Apple iPhone 17 256GB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,fine_print,status)
SELECT p.id,'devices','Apple iPhone Air 256GB','Monthly device financing shown with Bring-It-Back.',45.70,68.50,'Device pricing shown on supplied sheet. Plan, credit, term, stock and Bring-It-Back eligibility must be confirmed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Apple iPhone Air 256GB');
