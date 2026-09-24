import subprocess
import os

# Python 실행 경로 (사용자 시스템에 맞춤)
python_path = r"C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe"

# 개별 스크립트 경로 (절대경로로 지정)
scripts = [
    r"e:/Project/202410/www/pyFutures/DBUp_futures_1min.py",
    r"e:/Project/202410/www/pyFutures/DBUp_futures_xmin.py",
    r"e:/Project/202410/www/pyFutures/DBUp_futures_xday.py",
    r"e:/Project/202410/www/pyFutures/DBUp_rule_based_rowdata.py",
]

print("▶ 선물 데이터 업데이트 시작...\n")

failed_scripts = []

# Windows CP949 콘솔에서 자식 스크립트의 이모지 출력이 오류를 내지 않도록 설정
child_env = os.environ.copy()
child_env["PYTHONIOENCODING"] = "cp949:replace"

for script in scripts:
    print(f"▶ 실행 중: {script}")
    try:
        result = subprocess.run(
            [python_path, script],
            capture_output=True,
            text=True,
            encoding="cp949",
            errors="replace",
            env=child_env,
            check=True
        )
        print(result.stdout)
        print(f"▶ 완료: {script}\n")
    except subprocess.CalledProcessError as e:
        failed_scripts.append(script)
        print(f"▶ 오류 발생: {script}")
        if e.stdout:
            print(e.stdout)
        print(e.stderr)
        print("-" * 40)

if failed_scripts:
    print(f"▶ 파이프라인 종료: {len(failed_scripts)}개 스크립트 실패")
    for script in failed_scripts:
        print(f"  - {script}")
    raise SystemExit(1)

print("▶ 모든 업데이트 스크립트 정상 완료.")
