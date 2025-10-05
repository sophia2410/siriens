CREATE TABLE `futures_5min` (
	`date` DATE NOT NULL,
	`time` TIME NOT NULL,
	`datetime` DATETIME NOT NULL,
	`open` DECIMAL(10,2) NULL DEFAULT NULL,
	`high` DECIMAL(10,2) NULL DEFAULT NULL,
	`low` DECIMAL(10,2) NULL DEFAULT NULL,
	`close` DECIMAL(10,2) NULL DEFAULT NULL,
	`volume` BIGINT(20) NULL DEFAULT NULL,
	`sma_5` DECIMAL(10,3) NULL DEFAULT NULL,
	`sma_20` DECIMAL(10,3) NULL DEFAULT NULL,
	`sma_120` DECIMAL(10,3) NULL DEFAULT NULL,
	`rsi_14` DECIMAL(10,3) NULL DEFAULT NULL,
	PRIMARY KEY (`date`, `time`) USING BTREE,
	UNIQUE INDEX `datetime` (`datetime`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
;

CREATE TABLE `futures_60min` (
	`date` DATE NOT NULL,
	`time` TIME NOT NULL,
	`datetime` DATETIME NOT NULL,
	`open` DECIMAL(10,2) NULL DEFAULT NULL,
	`high` DECIMAL(10,2) NULL DEFAULT NULL,
	`low` DECIMAL(10,2) NULL DEFAULT NULL,
	`close` DECIMAL(10,2) NULL DEFAULT NULL,
	`volume` BIGINT(20) NULL DEFAULT NULL,
	`sma_5` DECIMAL(10,3) NULL DEFAULT NULL,
	`sma_20` DECIMAL(10,3) NULL DEFAULT NULL,
	`sma_120` DECIMAL(10,3) NULL DEFAULT NULL,
	`rsi_14` DECIMAL(10,3) NULL DEFAULT NULL,
	`bb_center` FLOAT NULL DEFAULT NULL,
	`bb_upper` FLOAT NULL DEFAULT NULL,
	`bb_lower` FLOAT NULL DEFAULT NULL,
	`ema_20` FLOAT NULL DEFAULT NULL,
	`ema_60` FLOAT NULL DEFAULT NULL,
	`macd` FLOAT NULL DEFAULT NULL,
	`macd_signal` FLOAT NULL DEFAULT NULL,
	`macd_hist` FLOAT NULL DEFAULT NULL,
	PRIMARY KEY (`date`, `time`) USING BTREE,
	UNIQUE INDEX `datetime` (`datetime`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
;


CREATE TABLE `futures_first_open_ind` (
	`date` DATE NOT NULL,
	`time` TIME NULL DEFAULT NULL,
	`open_price` DECIMAL(10,2) NULL DEFAULT NULL,
	`bb_center` DECIMAL(10,4) NULL DEFAULT NULL,
	`bb_upper` DECIMAL(10,4) NULL DEFAULT NULL,
	`bb_lower` DECIMAL(10,4) NULL DEFAULT NULL,
	`rsi14` DECIMAL(10,4) NULL DEFAULT NULL,
	`sma20` DECIMAL(10,4) NULL DEFAULT NULL,
	PRIMARY KEY (`date`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
;


CREATE OR REPLACE VIEW vw_first5m_1bar AS
SELECT
    f.date,
    f.high - f.low              AS range_5m,
    f.volume                    AS vol_5m,
    CASE WHEN f.close > f.open
         THEN 1 ELSE 0 END      AS up_5m,
    (f.close - f.open) / f.open AS ret_5m
FROM (
        /* 날짜별 첫 60 분봉 시각 */
        SELECT  date,
                MIN(datetime) AS first_dt
        FROM    futures_60min
        GROUP   BY date
) d
JOIN futures_5min f
  ON f.datetime = d.first_dt     -- 딱 첫 5 분봉 한 개
;


CREATE TABLE `morning_pfilter_trades` (
	`date` DATE NOT NULL,
	`entry_price` DECIMAL(10,4) NULL DEFAULT NULL,
	`exit_price` DECIMAL(10,4) NULL DEFAULT NULL,
	`direction` ENUM('LONG','SHORT') NOT NULL COLLATE 'utf8mb3_general_ci',
	`p_long` DECIMAL(6,4) NULL DEFAULT NULL,
	`tp` DECIMAL(10,4) NULL DEFAULT NULL,
	`sl` DECIMAL(10,4) NULL DEFAULT NULL,
	`outcome` ENUM('TP','SL','CLS') NOT NULL COLLATE 'utf8mb3_general_ci',
	`ret` DECIMAL(7,4) NULL DEFAULT NULL,
	`pt_profit` DECIMAL(12,4) NULL DEFAULT NULL,
	PRIMARY KEY (`date`, `direction`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
;


CREATE TABLE `morning_pfilter_grid` (
	`ph` DECIMAL(4,2) NOT NULL,
	`pl` DECIMAL(4,2) NOT NULL,
	`tp` DECIMAL(4,2) NOT NULL,
	`sl` DECIMAL(4,2) NOT NULL,
	`trades` INT(11) NULL DEFAULT NULL,
	`wins` INT(11) NULL DEFAULT NULL,
	`losses` INT(11) NULL DEFAULT NULL,
	`win_rate` DECIMAL(6,2) NULL DEFAULT NULL,
	`avg_R` DECIMAL(8,3) NULL DEFAULT NULL,
	`total_R` DECIMAL(10,3) NULL DEFAULT NULL,
	PRIMARY KEY (`ph`, `pl`, `tp`, `sl`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
;
