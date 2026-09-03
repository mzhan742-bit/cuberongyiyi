CREATE DATABASE IF NOT EXISTS team2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE team2026;

DROP TABLE IF EXISTS account;
CREATE TABLE account (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(20) NOT NULL,
  password varchar(100) NOT NULL,
  email longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  create_time timestamp NULL DEFAULT current_timestamp(),
  update_time timestamp NULL DEFAULT current_timestamp(),
  ban tinyint(1) NOT NULL DEFAULT 0,
  is_admin tinyint(1) NOT NULL DEFAULT 0,
  last_time_login timestamp NOT NULL DEFAULT '2002-07-30 17:00:00',
  last_time_logout timestamp NOT NULL DEFAULT '2002-07-30 17:00:00',
  ip_address varchar(50) DEFAULT NULL,
  active int(11) NOT NULL DEFAULT 1,
  thoi_vang int(11) NOT NULL DEFAULT 0,
  server_login int(11) NOT NULL DEFAULT -1,
  bd_player double DEFAULT 1,
  is_gift_box tinyint(1) DEFAULT 0,
  gift_time varchar(255) DEFAULT '0',
  reward longtext DEFAULT NULL,
  vnd int(11) NOT NULL DEFAULT 0,
  tongnap int(11) NOT NULL DEFAULT 0,
  token text NOT NULL,
  xsrf_token text NOT NULL,
  newpass text NOT NULL,
  luotquay int(11) NOT NULL DEFAULT 0,
  vang bigint(20) NOT NULL DEFAULT 0,
  event_point int(11) NOT NULL DEFAULT 0,
  vip int(11) NOT NULL DEFAULT 0,
  tichdiem int(11) NOT NULL DEFAULT 0,
  point_post int(11) NOT NULL DEFAULT 0,
  last_post int(11) NOT NULL DEFAULT 0,
  gioithieu int(11) DEFAULT NULL,
  xacnhan_gioitheu int(11) NOT NULL DEFAULT 0,
  baiviet int(11) NOT NULL DEFAULT 0,
  xacminh int(11) NOT NULL DEFAULT 0,
  admin int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY(id), UNIQUE KEY(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO account(username,password,email,token,xsrf_token,newpass,is_admin,vnd,tongnap) VALUES ('admin','1','', '', '', '',1,100000,50000);

DROP TABLE IF EXISTS posts;
CREATE TABLE posts (
 id int(11) NOT NULL AUTO_INCREMENT,
 tieude varchar(75) NOT NULL,
 noidung text NOT NULL,
 username varchar(50) NOT NULL,
 created_at timestamp NOT NULL DEFAULT current_timestamp(),
 theloai int(11) NOT NULL DEFAULT 0,
 ghimbai int(11) NOT NULL DEFAULT 0,
 image varchar(255) DEFAULT NULL,
 trangthai int(11) NOT NULL DEFAULT 0,
 tinhtrang int(11) NOT NULL DEFAULT 0,
 `like` int(11) NOT NULL DEFAULT 0,
 PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO posts(tieude,noidung,username,ghimbai) VALUES ('Bai cu team2026','Noi dung cu','admin',1);

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
 id int(11) NOT NULL AUTO_INCREMENT,
 name varchar(255) NOT NULL,
 refNo varchar(255) NOT NULL,
 date datetime NOT NULL,
 card_serial varchar(255) DEFAULT NULL,
 card_pin varchar(255) DEFAULT NULL,
 declared_amount int(11) NOT NULL,
 api_declared_value int(11) DEFAULT NULL,
 detected_value int(11) DEFAULT NULL,
 received_amount_from_api int(11) DEFAULT NULL,
 final_credited_amount int(11) DEFAULT 0,
 status_text varchar(255) NOT NULL,
 api_status_code varchar(50) NOT NULL,
 api_message text DEFAULT NULL,
 card_telco varchar(50) DEFAULT NULL,
 is_credited tinyint(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
 Title varchar(100) DEFAULT 'Test', Description longtext, Keywords longtext, SiteKey varchar(100), SecretKey varchar(100),
 ServerName varchar(100), Fanpage varchar(100), `Group` varchar(100), Zalo varchar(100), EmailSupport varchar(50),
 AccountBank varchar(50), PasswordBank varchar(50), NumberBank int(11), NameBank varchar(50), Android varchar(50), Windows varchar(50), IPhone varchar(50), Java varchar(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO settings(AccountBank,PasswordBank,NumberBank) VALUES ('bankuser','bankpass',123456789);
