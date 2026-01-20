function updateLiveKpis(){
  const row = getDecisionRow();

  const hhmm = row?.datetime ? row.datetime.slice(11,16) : '-';

  const open1  = row?.open  != null ? Number(row.open)  : NaN;
  const high1  = row?.high  != null ? Number(row.high)  : NaN;
  const low1   = row?.low   != null ? Number(row.low)   : NaN;
  const close1 = row?.close != null ? Number(row.close) : NaN;

  const closeTxt = Number.isFinite(close1) ? close1.toFixed(2) : '-';

  // ✅ 주문 입력 패널(결정봉)
  setText('kpiNowDt', hhmm);
  setText('kpiNowClose', closeTxt);
  setText('kpiNowOpen', Number.isFinite(open1) ? open1.toFixed(2) : '-');
  setText('kpiNowHigh', Number.isFinite(high1) ? high1.toFixed(2) : '-');
  setText('kpiNowLow',  Number.isFinite(low1)  ? low1.toFixed(2)  : '-');

  // 포지션/평단
  const posQty = Number(simDayState?.pos_qty || 0);
  const avg = (simDayState?.avg_price != null) ? Number(simDayState.avg_price) : NaN;

  setText('kpiPos', posQty === 0 ? 'FLAT' : (posQty > 0 ? `LONG x${posQty}` : `SHORT x${Math.abs(posQty)}`));
  setText('kpiAvg', Number.isFinite(avg) ? avg.toFixed(2) : '-');

  // 이격(내 포지션 기준 +가 유리)
  let gapPts = null;
  if (posQty !== 0 && Number.isFinite(avg) && Number.isFinite(close1)){
    gapPts = (posQty > 0) ? (close1 - avg) : (avg - close1);
  }
  setText('kpiGapPts', (gapPts == null) ? '-' : gapPts.toFixed(2));
  setText('kpiGapAmt', (gapPts == null) ? '-' : fmtInt(gapPts * POINT_VALUE));

  // 총손익(pts): realized + unrealized
  const realizedPts = Number(simDayState?.pnl_points || 0);
  let unrealPts = 0;
  if (posQty !== 0 && Number.isFinite(avg) && Number.isFinite(close1)){
    unrealPts = (close1 - avg) * posQty; // posQty 부호로 숏 자동 반영
  }
  const totalPts = realizedPts + unrealPts;
  setText('kpiPnlPts', Number.isFinite(totalPts) ? totalPts.toFixed(2) : '-');

  // 순손익(원): pts*POINT_VALUE - 수수료누적
  const feeTotal = Number(simDayState?.fee_total || 0);
  const netAmt = Math.round(totalPts * POINT_VALUE) - feeTotal;
  setText('kpiNetAmt', Number.isFinite(netAmt) ? fmtInt(netAmt) : '-');
}
