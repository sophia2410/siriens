import subprocess

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

for script in scripts:
    print(f"▶ 실행 중: {script}")
    try:
        result = subprocess.run(
            [python_path, script],
            capture_output=True,
            text=True,
            check=True
        )
        print(result.stdout)
        print(f"▶ 완료: {script}\n")
    except subprocess.CalledProcessError as e:
        print(f"▶ 오류 발생: {script}")
        print(e.stderr)
        print("-" * 40)

print("🎉 모든 업데이트 스크립트 실행 완료.")
