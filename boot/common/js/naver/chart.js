
var pollingInterval = 2000;
var timer = null;
var pollingAjax = null;
var itemcodes = '';

var startTimer = function() {
	clearTimer();
	timer = setInterval('doPolling()', pollingInterval);
}

var clearTimer = function() {
	if (pollingAjax) {
		pollingAjax.abort();
		pollingAjax = null;
	}

	timer = window.clearInterval(timer);
}

var doPolling = function() {
	var pollingApiUrl = "https://polling.finance.naver.com/api/realtime?query=SERVICE_ITEM:" + "036540"

	if (requestType == 'recent' && itemcodes != "") {
		pollingApiUrl += "|" + "SERVICE_RECENT_ITEM:" + itemcodes;
	} else if (requestType == 'mystock' && itemcodes != ""){
		pollingApiUrl += "|" + "SERVICE_MYSTOCK_ITEM:" + itemcodes;
	}

	pollingAjax = jindo.$Ajax(pollingApiUrl, {
		type : 'jsonp',
		jsonp_charset : "euc-kr",
		onload : function(response) {
			if(response != null && response.readyState() == 4) {
				refreshQuote(response.json());
				startTimer();
			}
		},
		timeout : 2,
		ontimeout : function() {
			startTimer();
		},
		async : true
	});
	pollingAjax.request();
}

function displayTime(ms, time) {
	var result;

	var utcDate = new Date(time);
	utcDate.setMinutes(utcDate.getMinutes() + utcDate.getTimezoneOffset());
	utcDate.setHours(utcDate.getHours() + 9);

	var oDate = jindo.$Date(utcDate);

	if (ms == "PREOPEN") {
		closeDate = oDate.format('Y.m.d')
		result = '<em class="date">' + oDate.format('Y.m.d') + ' <span>기준(개장전)</span></em> ';
	} else if (ms == "CLOSE") {
		result = '<em class="date">' + closeDate + ' <span>기준(장마감)</span></em> ';
	} else {
		closeDate = oDate.format('Y.m.d')
		result = '<em class="date">' + oDate.format('Y.m.d H:i') + ' <span>기준(장중)</span></em> ';
	}

	return result;
}

/**
 * 우측상단 투자정보 영역 중, NAV 값 갱신
 */
function refreshEtfNav(etfNav) {
	var elEtfNav = jindo.$Element(jindo.$$.getSingle("#on_board_last_nav"));
	if (elEtfNav != null) {
		if (etfNav != null) {
			elEtfNav.html("<em><strong>" + changeNumberFormat(etfNav.toFixed(0)) + "</strong></em>");
		} else {
			elEtfNav.html("<em><strong>N/A</strong></em>");
		}
	}
}

function refreshInvestmentSummary(nv) {
	if (!(true && false && false)) {
		return;
	}

	var averageBuyingPrice = 0;
	var holdingShares = 0;

	var elEvaluationProfitAmount = jindo.$Element(jindo.$$.getSingle("#evaluation_profit_amount"));
	// 평가손익 = (nv - 평균매입가) * 보유수량
	var evaluationProfitAmount = (nv - 0) * 0;
	// 평가수익률 = 평가손익 / (평균매입가 * 보유수량) * 100
	var evaluationProfitRate = evaluationProfitAmount / (0 * 0) * 100;

	var sPointClass = "";
	var sSign = "";
	if (evaluationProfitAmount > 0) {
		sPointClass = "f_up";
		sSign = "+";
	} else {
		sPointClass = "f_down";
		sSign = "";
	}

	var evaluationProfitAmoutText = ""
	if (evaluationProfitAmount > 10000000000) {
		var evaluationProfitAmountDivideBillion = evaluationProfitAmount / 100000000;
		evaluationProfitAmountText = "<em>" + sSign + changeNumberFormat(evaluationProfitAmountDivideBillion.toFixed(0)) + "억</em>";
	} else {
		evaluationProfitAmountText = "<em>" + sSign + changeNumberFormat(evaluationProfitAmount) + "</em>";
	}

	if (elEvaluationProfitAmount != null) {
		elEvaluationProfitAmount.removeClass("f_up");
		elEvaluationProfitAmount.removeClass("f_down");
		elEvaluationProfitAmount.addClass(sPointClass);
		elEvaluationProfitAmount.html(evaluationProfitAmountText);
	}

	var elEvaluationProfitRate = jindo.$Element(jindo.$$.getSingle("#evaluation_profit_rate"));
	if (elEvaluationProfitRate != null) {

		elEvaluationProfitRate.removeClass("f_up");
		elEvaluationProfitRate.removeClass("f_down");
		elEvaluationProfitRate.addClass(sPointClass);

		elEvaluationProfitRate.html("<em>" + sSign + changeNumberFormat(evaluationProfitRate.toFixed(2)) + "%</em>");
	}
}


function refreshCompanyValue(json) {
	var sPer = "N/A";
	var sKrxPer = "N/A";
	/* if ((json.per !== undefined) && (json.per !== null)) {
		sPer = converToFixedPointNotation(json.per, 2);
	}

	jindo.$A(jindo.$$("#_per")).forEach(function(v) {
		jindo.$Element(v).text(sPer);
	}, this); */

	if ((json.eps !== undefined) && (json.eps !== null)) {
		sPer = converToFixedPointNotation(json.nv / json.eps, 2);
	}

	jindo.$A(jindo.$$("#_per")).forEach(function(v) {
		jindo.$Element(v).text(sPer);
	}, this);

	if ((json.keps !== undefined) && (json.keps !== null)) {
		sKrxPer = converToFixedPointNotation(json.nv / json.keps, 2);
	}

	jindo.$A(jindo.$$("#krx_per")).forEach(function(v) {
		jindo.$Element(v).text(sKrxPer);
	}, this);

	var sCnsPer = "N/A";
	if ((json.cnsEps !== undefined) && (json.cnsEps !== null)) {
		sCnsPer = converToFixedPointNotation(json.nv / json.cnsEps, 2);
	}

	jindo.$A(jindo.$$("#_cns_per")).forEach(function(v) {
		jindo.$Element(v).text(sCnsPer);
	}, this);

	var sPbr = "N/A";
	if ((json.bps !== undefined) && (json.bps !== null)) {
		var bps = converToFixedPointNotation(json.bps, 0)
		sPbr = converToFixedPointNotation(json.nv / bps, 2);
	}

	jindo.$A(jindo.$$("#_pbr")).forEach(function(v) {
		jindo.$Element(v).text(sPbr);
	}, this);

	var sDvr = "N/A";
	if ((json.dv !== undefined) && (json.dv !== null)) {
		var dv = converToFixedPointNotation(json.dv, 0)
		sDvr = converToFixedPointNotation((dv * 100)/ json.nv, 2);
	}

	jindo.$A(jindo.$$("#_dvr")).forEach(function(v) {
		jindo.$Element(v).text(sDvr);
	}, this);
}

function refreshQuote(res) {
	if(res != null && res.resultCode == 'success') {
		pollingInterval = res.result.pollingInterval;

		for (var index = 0 ; index < res.result.areas.length ; index++) {
			if (res.result.areas[index].name == "SERVICE_ITEM") {
				var ms = "NOT DEFINED";
				ms = res.result.areas[index].datas[0].ms;
				document.getElementById("time").innerHTML = displayTime(ms, res.result.time);

				jindo.$Element(jindo.$$.getSingle("#chart_area .rate_info")).html(jindo.$Template("summaryTpl").process(res.result.areas[index].datas[0]));

				refreshInvestmentSummary(res.result.areas[index].datas[0].nv);
				refreshEtfNav(res.result.areas[index].datas[0].nav);
				refreshCompanyValue(res.result.areas[index].datas[0]);
			} else if (res.result.areas[index].name == "SERVICE_RECENT_ITEM") {
				renderRecentAreaRealtime("recent", res.result.areas[index]);
			} else if (res.result.areas[index].name == "SERVICE_MYSTOCK_ITEM") {
				renderRecentAreaRealtime("mystock", res.result.areas[index]);
			}
		}
	}
}

function numberFont(value) {
	value = value + "";
	var result = "";

	for (i = 0; i < value.length; i++) {
		var tmpChar = value.charAt(i);

		result += '<span class="';
		if (tmpChar == ".") {
			result += "jum";
		} else if (tmpChar == ",") {
			result += "shim";
		} else {
			result += "no" + tmpChar;
		}
		result += '">' + tmpChar + '</span>';
	}

	return result;
}

function changeNumberFormat(vNumber){
	var sUnderNumber = "";
	var sNumberString = vNumber || 0;
	sNumberString = (typeof sNumberString != "String") ? String(sNumberString) : sNumberString;

	if(sNumberString.indexOf(".") > -1){
		var aNumber = sNumberString.split(".");
		sNumberString = aNumber[0];
		sUnderNumber = "." + aNumber[1];
	}

	return sNumberString.replace(/(\d)(?=(\d{3})+$)/igm, "$1,") + sUnderNumber;
}

function converToFixedPointNotation (nNumber, nDecimalLength){
	return parseFloat(nNumber).toFixed(nDecimalLength || 0);
}

// 1분마다 정보 업데이트
var updateInformationInterval = 60000;
var informationTimer = null;
var oUpdateAjax = null;

var startInformationTimer = function() {
	clearInformationTimer();
	informationTimer = setInterval('doUpdateInformation()', updateInformationInterval);
}

var clearInformationTimer = function() {
	if (oUpdateAjax) {
		oUpdateAjax.abort();
		oUpdateAjax = null;
	}

	informationTimer = window.clearInterval(informationTimer);
}

var doUpdateInformation = function() {
	var sApiUrl = "https://api.finance.naver.com/service/itemSummary.naver?itemcode=036540"

	oUpdateAjax = jindo.$Ajax(sApiUrl, {
		type : 'jsonp',
		jsonp_charset : "utf-8",
		onload : function(response) {
			if(response != null && response.readyState() == 4) {
				var json = response.json();

				if (json.now == undefined) {
					startInformationTimer();
					return;
				}

				var sMarketSum = (json.marketSum + "");
				sMarketSum = sMarketSum.substring(0, sMarketSum.length - 2);
				sMarketSum = changeNumberFormat(sMarketSum);

				var sPer = changeNumberFormat(json.per);
				var sEps = changeNumberFormat(json.eps);
				var sNowVal = changeNumberFormat(json.now);
				var sDiff = changeNumberFormat(json.diff);
				var sRate = changeNumberFormat(json.rate) + "%";
				var sQuant = changeNumberFormat(json.quant);
				var sAmount = changeNumberFormat(json.amount);
				var sHigh = changeNumberFormat(json.high);
				var sLow = changeNumberFormat(json.low);

				if (sRate == "0%") {
					sRate = "0.00%";
				}

				if (json.per == undefined) {
					sPer = "N/A";
				}

				if (json.eps == undefined) {
					sEps = "N/A";
				}

				jindo.$A(jindo.$$("#_sise_market_sum")).forEach(function(v) {
					jindo.$Element(v).text(sMarketSum);
				}, this);

				jindo.$A(jindo.$$("#_sise_per")).forEach(function(v) {
					jindo.$Element(v).text(sPer);
				}, this);

				jindo.$A(jindo.$$("#_sise_eps")).forEach(function(v) {
					jindo.$Element(v).text(sEps);
				}, this);

				jindo.$A(jindo.$$("#_nowVal")).forEach(function(v) {
					jindo.$Element(v).text(sNowVal);
				}, this);

				jindo.$A(jindo.$$("#_diff")).forEach(function(v) {
					var sFormat = null;
					var sDiffToDisplay = sDiff.replace("-", "");

					if (json.risefall == 1) {
						sFormat = "<em class=\"bu_p bu_pup2\" style=\"margin:0 4px 0 0\"><span class=\"blind\">상한</span></em><span class=\"tah p11 red01\">%s</span>";
					} else if (json.risefall == 2) {
						sFormat = "<em class=\"bu_p bu_pup\" style=\"margin:0 4px 0 0\"><span class=\"blind\">상승</span></em><span class=\"tah p11 red01\">%s</span>";
					} else if (json.risefall == 3) {
						sFormat = "<span class=\"tah p11\">%s</span>";
					} else if (json.risefall == 4) {
						sFormat = "<em class=\"bu_p bu_pdn2\" style=\"margin:0 4px 0 0\"><span class=\"blind\">하한</span></em><span class=\"tah p11 nv01\">%s</span>";
					} else {
						sFormat = "<em class=\"bu_p bu_pdn\" style=\"margin:0 4px 0 0\"><span class=\"blind\">하락</span></em><span class=\"tah p11 nv01\">%s</span>";
					}

					var sHtml = jindo.$S(sFormat).format(sDiffToDisplay);

					jindo.$Element(v).html(sHtml);
				}, this);

				jindo.$A(jindo.$$("#_rate")).forEach(function(v) {
					var sCss = "red01";
					var sRateToDisplay = sRate;

					if (sRate.indexOf("-") > -1) {
						sCss = "nv01";
					} else if (sRate.indexOf("0.00") > -1) {
						sCss = "";
					} else {
						sRateToDisplay = "+" + sRateToDisplay;
					}

					var sHtml = "<span class=\"tah p11 " + sCss + "\">" + sRateToDisplay + "</span>";
					jindo.$Element(v).html(sHtml);
				}, this);

				jindo.$A(jindo.$$("#_quant")).forEach(function(v) {
					jindo.$Element(v).text(sQuant);
				}, this);

				jindo.$A(jindo.$$("#_amount")).forEach(function(v) {
					jindo.$Element(v).text(sAmount);
				}, this);

				jindo.$A(jindo.$$("#_high")).forEach(function(v) {
					jindo.$Element(v).text(sHigh);
				}, this);

				jindo.$A(jindo.$$("#_low")).forEach(function(v) {
					jindo.$Element(v).text(sLow);
				}, this);

				startInformationTimer();
			}
		},
		timeout : 2,
		ontimeout : function() {
			startInformationTimer();
		},
		async : true
	});
	oUpdateAjax.request();
}

var dateTime = "20230411100318";
var closeDate = dateTime.substring(0,4) + "." + dateTime.substring(4,6) + "." + dateTime.substring(6,8);



/**
 * 선택한 타입(1일, 일주일, 3개월, 1년, 3년, 5년 , 10년)에 따라 이미지 차트 변경
 *
 * @param chartType 차트종류(1일, 일주일, 3개월, 1년, 3년, 5년, 10년)
 */
// 선 차트 노출
function showChart(target) {
    jindo.$A(jindo.$$("dl.line dd ul li")).forEach(function(v) {
        if (jindo.$Element(v).className() == target) {
            jindo.$Element(v).child()[0].addClass("on");
            jindo.$Element(jindo.$$.getSingle("#img_chart_area")).attr("src", "https://ssl.pstatic.net/imgfinance/chart/item/area/" + target + "/036540.png?sidcode=1681175003445");
        } else {
            jindo.$Element(v).child()[0].removeClass("on");
        }
    });

    jindo.$A(jindo.$$("dl.bar dd ul li")).forEach(function(v) {
        jindo.$Element(v).child()[0].removeClass("on");
    });

}

/**
 * 선택한 타입(일봉, 주봉, 월봉)에 따라 이미지 차트 변경
 *
 * @param chartType 차트종류(일봉, 주봉, 월봉)
 */
// 봉 차트 노출
function showBarChart(target) {
    jindo.$A(jindo.$$("dl.bar dd ul li")).forEach(function(v) {
        if (jindo.$Element(v).className() == target) {
            jindo.$Element(v).child()[0].addClass("on");
            jindo.$Element(jindo.$$.getSingle("#img_chart_area")).attr("src", "https://ssl.pstatic.net/imgfinance/chart/item/candle/" + target + "/036540.png?sidcode=1681175003445");
        } else {
            jindo.$Element(v).child()[0].removeClass("on");
        }
    });

    jindo.$A(jindo.$$("dl.line dd ul li")).forEach(function(v) {
        jindo.$Element(v).child()[0].removeClass("on");
    });
}

/**
 * 플래시 지원여부 확인
 */
function isInstalledFlash() {
    var aFlash = jindo.$Agent().flash();
    var bResult = false;

    if (aFlash != null && aFlash.installed) {
        bResult = true;
    }

    return bResult;
}

/**
 * 플래시 지원여부에 따라 차트 노출
 */
function initChartArea(flashAreaClassName, htmlAreaClassName) {
    var eFlashChartArea = jindo.$Element(jindo.$$.getSingle("." + flashAreaClassName));
    var eImgChartArea = jindo.$Element(jindo.$$.getSingle("." + htmlAreaClassName));

    if (eFlashChartArea != null && eImgChartArea != null) {
        if (isInstalledFlash()) {
            eImgChartArea.hide();
        } else {
            eFlashChartArea.hide();
        }
    }
}