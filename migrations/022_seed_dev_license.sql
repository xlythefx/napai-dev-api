-- Seed dev fast-path serial that matches the Flutter app's hardcoded _devSerial.
INSERT IGNORE INTO license_keys (serial, note)
VALUES ('AAAA-BBBB-CCCC-DDDD', 'dev fast-path key (matches Flutter _devSerial)');
