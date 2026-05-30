//@version=5
indicator("SMA20 Touch/GAP -> Box stays (ends at next EVENT)", overlay=true, max_lines_count=500, max_labels_count=500)

// ===== Inputs =====
len        = input.int(20, "SMA Length", minval=1)
tickPt     = input.float(0.05, "Tick Size (pt)", step=0.01)
ticks      = input.int(4, "Offset ticks (Entry/Stop)", minval=0)
useExpire  = input.bool(true, "Use expire(30m) styling")
expireBars = input.int(6, "Expire bars (5m bars)", minval=1)
maxBoxes   = input.int(30, "Max boxes to keep", minval=1, maxval=200)

showMarkers     = input.bool(true, "Show X/R/G markers")
markerPadTicks  = input.int(2, "Marker pad ticks", minval=0)
useLooseCross   = input.bool(true, "Detect close cross even without touch")

// ===== SMA =====
smaVal  = ta.sma(close, len)
smaPrev = smaVal[1]
plot(smaVal, "SMA20", linewidth=2)

// ===== Event definitions =====
touch = (low <= smaVal) and (high >= smaVal)

// 기존 touch 기반
crossUpClose   = touch and open < smaVal and close > smaVal
crossDownClose = touch and open > smaVal and close < smaVal
rejectSupport  = touch and open > smaVal and close > smaVal
rejectResist   = touch and open < smaVal and close < smaVal

// 갭(띄어넘기) 시작
gapUpOpenStart   = (not na(smaPrev)) and (close[1] < smaPrev) and (open > smaPrev)
gapDownOpenStart = (not na(smaPrev)) and (close[1] > smaPrev) and (open < smaPrev)

// touch 없이 종가 교차(재돌파 같은 케이스 커버)
closeCrossUp   = useLooseCross and (not na(smaPrev)) and (close > smaVal) and (close[1] <= smaPrev)
closeCrossDown = useLooseCross and (not na(smaPrev)) and (close < smaVal) and (close[1] >= smaPrev)

eventLong  = crossUpClose or rejectSupport or gapUpOpenStart or closeCrossUp
eventShort = crossDownClose or rejectResist or gapDownOpenStart or closeCrossDown

eventNow = eventLong or eventShort
eventDir = eventLong ? 1 : eventShort ? -1 : 0

// ===== Pending (EVENT -> next bar BASE) =====
var int pendingDir = 0
var int eventBarIndex = na
pendingLocked = pendingDir != 0

// ===== Current box state =====
var float boxHigh = na
var float boxLow  = na
var float baseMid = na
var float entryLine = na
var float stopLine  = na
var int   boxDir  = 0
var int   boxFromIndex = na
var bool  boxExpired = false

// ===== Current drawing objects (only the "active/current" one) =====
var line  curHi   = na
var line  curLo   = na
var line  curMid  = na
var line  curEnt  = na
var line  curStop = na
var label curEntLb  = na
var label curStopLb = na
var label curExpLb  = na

hasCurrentBox = not na(curHi)

// ===== History arrays (ended boxes are kept here, not deleted) =====
var line[]  histHi   = array.new_line()
var line[]  histLo   = array.new_line()
var line[]  histMid  = array.new_line()
var line[]  histEnt  = array.new_line()
var line[]  histStop = array.new_line()
var label[] histEntLb  = array.new_label()
var label[] histStopLb = array.new_label()
var label[] histExpLb  = array.new_label()

// ===== Helper: trim oldest boxes when exceeding maxBoxes =====
trimNeeded = array.size(histHi) > maxBoxes
if trimNeeded
    while array.size(histHi) > maxBoxes
        line.delete(array.shift(histHi))
        line.delete(array.shift(histLo))
        line.delete(array.shift(histMid))
        line.delete(array.shift(histEnt))
        line.delete(array.shift(histStop))
        label.delete(array.shift(histEntLb))
        label.delete(array.shift(histStopLb))
        label.delete(array.shift(histExpLb))

// ===== (1) EVENT 발생 시: 현재 박스를 "이벤트봉에서 끝"내고, pending 등록 =====
if (not pendingLocked) and eventNow
    // 현재 박스가 있으면 이벤트봉에서 종료(지우지 않음)
    if hasCurrentBox
        line.set_x2(curHi,   bar_index)
        line.set_x2(curLo,   bar_index)
        line.set_x2(curMid,  bar_index)
        line.set_x2(curEnt,  bar_index)
        line.set_x2(curStop, bar_index)

        // 종료 스타일(회색 점선)
        line.set_style(curHi,   line.style_dashed)
        line.set_style(curLo,   line.style_dashed)
        line.set_style(curMid,  line.style_dashed)
        line.set_style(curEnt,  line.style_dashed)
        line.set_style(curStop, line.style_dashed)

        line.set_color(curHi,   color.gray)
        line.set_color(curLo,   color.gray)
        line.set_color(curMid,  color.gray)
        line.set_color(curEnt,  color.gray)
        line.set_color(curStop, color.gray)

        // 엔트리/스탑 라벨도 이벤트봉에서 고정 + 회색
        if not na(curEntLb)
            label.set_x(curEntLb, bar_index)
            label.set_color(curEntLb, color.gray)
            label.set_textcolor(curEntLb, color.white)
        if not na(curStopLb)
            label.set_x(curStopLb, bar_index)
            label.set_color(curStopLb, color.gray)
            label.set_textcolor(curStopLb, color.white)
        // 만료 라벨은 그대로 두고(만료 시점 표시), 삭제 안 함

        // 히스토리로 저장(이렇게 해야 오래 쓰면 오래된 것 삭제도 가능)
        array.push(histHi,   curHi)
        array.push(histLo,   curLo)
        array.push(histMid,  curMid)
        array.push(histEnt,  curEnt)
        array.push(histStop, curStop)
        array.push(histEntLb,  curEntLb)
        array.push(histStopLb, curStopLb)
        array.push(histExpLb,  curExpLb)

        // 현재 박스 핸들 해제(화면엔 남아있음)
        curHi := na
        curLo := na
        curMid := na
        curEnt := na
        curStop := na
        curEntLb := na
        curStopLb := na
        curExpLb := na

        boxHigh := na
        boxLow := na
        baseMid := na
        entryLine := na
        stopLine := na
        boxDir := 0
        boxFromIndex := na
        boxExpired := false

    // 이벤트를 pending으로 등록(다음 봉이 기준봉)
    pendingDir := eventDir
    eventBarIndex := bar_index

// ===== BASE bar 판단 =====
isBaseBar = (pendingDir != 0) and (not na(eventBarIndex)) and (bar_index == eventBarIndex + 1)

// ===== (2) BASE bar에서 새 박스 생성 (이전 박스 삭제 X, 누적) =====
boxJustSet = false
if isBaseBar
    boxHigh := high
    boxLow  := low
    baseMid := (boxHigh + boxLow) / 2.0

    off = ticks * tickPt

    // ✅ 네 규칙 유지
    // LONG: Entry = boxHigh + off, Stop = boxHigh - off
    // SHORT: Entry = boxLow  - off, Stop = boxLow  + off
    if pendingDir == 1
        entryLine := boxHigh + off
        stopLine  := boxHigh - off
    else
        entryLine := boxLow - off
        stopLine  := boxLow + off

    boxDir := pendingDir
    boxFromIndex := bar_index
    boxExpired := false

    // 새 박스 라인 생성(현재 박스로 추적)
    x1 = boxFromIndex
    x2 = bar_index

    curHi   := line.new(x1, boxHigh, x2, boxHigh, extend=extend.none, color=color.gray, style=line.style_solid, width=1)
    curLo   := line.new(x1, boxLow,  x2, boxLow,  extend=extend.none, color=color.gray, style=line.style_solid, width=1)
    curMid  := line.new(x1, baseMid, x2, baseMid, extend=extend.none, color=color.gray, style=line.style_dashed, width=1)
    // curEnt  := line.new(x1, entryLine, x2, entryLine, extend=extend.none, color=color.yellow, style=line.style_solid, width=2)
    // curStop := line.new(x1, stopLine,  x2, stopLine,  extend=extend.none, color=color.red,    style=line.style_solid, width=2)

    // dirTxt = boxDir == 1 ? "LONG" : "SHORT"
    // curEntLb  := label.new(x2, entryLine, dirTxt + " ENTRY", yloc=yloc.price, style=label.style_label_left, size=size.tiny, color=color.yellow, textcolor=color.black)
    // curStopLb := label.new(x2, stopLine,  dirTxt + " STOP",  yloc=yloc.price, style=label.style_label_left, size=size.tiny, color=color.red,    textcolor=color.white)

    label.delete(curExpLb)
    curExpLb := na

    // pending clear
    pendingDir := 0
    eventBarIndex := na
    boxJustSet := true

// ===== (3) 만료(30m) 표시: 박스는 계속 연장되지만, 스타일만 회색 점선으로 바꿈 =====
if useExpire and (not boxExpired) and (not na(boxFromIndex)) and (not na(curHi))
    if bar_index >= boxFromIndex + expireBars
        boxExpired := true

        line.set_style(curHi,   line.style_dashed)
        line.set_style(curLo,   line.style_dashed)
        line.set_style(curMid,  line.style_dashed)
        // line.set_style(curEnt,  line.style_dashed)
        // line.set_style(curStop, line.style_dashed)

        line.set_color(curHi,   color.gray)
        line.set_color(curLo,   color.gray)
        line.set_color(curMid,  color.gray)
        // line.set_color(curEnt,  color.gray)
        // line.set_color(curStop, color.gray)

        // if not na(curEntLb)
        //     label.set_color(curEntLb, color.gray)
        //     label.set_textcolor(curEntLb, color.white)
        // if not na(curStopLb)
        //     label.set_color(curStopLb, color.gray)
        //     label.set_textcolor(curStopLb, color.white)

        label.delete(curExpLb)
        curExpLb := label.new(bar_index, baseMid, "EXPIRED", yloc=yloc.price, style=label.style_label_left, size=size.tiny, color=color.gray, textcolor=color.white)

// ===== (4) 현재 박스는 계속 연장(다음 EVENT가 올 때까지) =====
if not na(curHi)
    line.set_x2(curHi,   bar_index)
    line.set_x2(curLo,   bar_index)
    line.set_x2(curMid,  bar_index)
    // line.set_x2(curEnt,  bar_index)
    // line.set_x2(curStop, bar_index)

    // if not na(curEntLb)
    //     label.set_x(curEntLb, bar_index)
    // if not na(curStopLb)
    //     label.set_x(curStopLb, bar_index)
    // EXPIRED 라벨은 "만료 시점" 표시라서 따라다니지 않게 둠

// ===== Markers (X/R/G) =====
pad = markerPadTicks * tickPt

if showMarkers and crossUpClose
    label.new(bar_index, low - pad, "X↑", yloc=yloc.price, style=label.style_label_up, color=color.lime, textcolor=color.white, size=size.tiny)
if showMarkers and crossDownClose
    label.new(bar_index, high + pad, "X↓", yloc=yloc.price, style=label.style_label_down, color=color.red, textcolor=color.white, size=size.tiny)
if showMarkers and rejectSupport
    label.new(bar_index, low - pad, "R↑", yloc=yloc.price, style=label.style_label_up, color=color.teal, textcolor=color.white, size=size.tiny)
if showMarkers and rejectResist
    label.new(bar_index, high + pad, "R↓", yloc=yloc.price, style=label.style_label_down, color=color.orange, textcolor=color.white, size=size.tiny)
if showMarkers and gapUpOpenStart
    label.new(bar_index, low - pad, "G↑", yloc=yloc.price, style=label.style_label_up, color=color.green, textcolor=color.white, size=size.tiny)
if showMarkers and gapDownOpenStart
    label.new(bar_index, high + pad, "G↓", yloc=yloc.price, style=label.style_label_down, color=color.maroon, textcolor=color.white, size=size.tiny)

// data window
plot(baseMid, "BaseMid", display=display.none)
plot(entryLine, "EntryLine", display=display.none)
plot(stopLine,  "StopLine",  display=display.none)
