/*!
 * Common JavaScript
 * @author Sophia
 */

// 키워드 불러오기
function comAjaxKeywordCd(obj, fg) {
	$.ajax({ 
		type : "POST",
		url : "/boot/common/ajax/ajaxKeyword.php",
		dataType : "json",
		data : {fg : fg },
		success : function(data){
			//$("#keyword_cd").html(""); // 중복 제거를 위한 select 박스 초기화
			obj.html(""); // 중복 제거를 위한 select 박스 초기화

			obj.append("<option value=''>--reset option (" + fg +")--</option>");
			
			for(var i=0; i<data.length; i++){
				//$("#keyword_cd").append('<option value="' + data[i].keyword_cd + '">' + data[i].keyword_nm + '</option>');
				obj.append('<option value="' + data[i].keyword_cd + '">' + data[i].keyword_nm + '</option>');
			}
		},
		error: function(request,status,error){
			alert(status);
			alert(error);
		},
	})
}

function comFilterChange(obj) {
	obj.value = "";
	// obj.focus();

	const options = obj.options;
	for (let i = 0; i < options.length; i++) {
		options[i].style.display = "";
	}
}

function comFilterInput(obj) {
	const selectBoxId = obj.className;
	const selectBox = document.getElementById(selectBoxId);
	const options = selectBox.options;
	for (let i = 0; i < options.length; i++) {
	  const option = options[i];
	  if (option.text.toLowerCase().indexOf(obj.value.toLowerCase()) > -1) {
		option.style.display = "";
	  } else {
		option.style.display = "none";
	  }
	}
}

// 체크박스 전체 체크/해제
function comTgAllCk(obj){
	var ckbx = document.getElementsByClassName('chk');
	for(i=0;i<ckbx.length;i++){
		ckbx[i].checked=obj.checked;
	}
}

// 체크박스 전체 체크해제
function comAllUnCk(){
	var ckbx = document.getElementsByClassName('chk');
	for(i=0;i<ckbx.length;i++){
		ckbx[i].checked=false;
	}
}

function comPopupNews(link) {
	window.open(link,'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
}