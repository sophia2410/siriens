import os
import re
import pypandoc

# ✅ MiKTeX xelatex 경로 수동 등록
os.environ["PATH"] += os.pathsep + r"C:\Program Files\MiKTeX\miktex\bin\x64"

# ✅ 설정
input_dir = r"D:\Obsidian\Trader Sophia\♠ Daily Market\futures\전략B"
image_dir = r"D:/Obsidian/Trader Sophia/90 Attachments"
output_dir = r"E:\Project\202410\data\_futures\자동매매\pdf_exports"
os.makedirs(output_dir, exist_ok=True)

# ✅ 세로 출력할 파일명 목록 (확장자 제외, 정확히 일치해야 적용됨)
portrait_names = {"양양양", "양양음", "양음양", "양음음", "음양양", "음양음", "음음양", "음음음"}

# ✅ Obsidian 이미지 링크 변환 함수
def convert_obsidian_image_links(md_text):
    # Obsidian 이미지 문법 → 일반 마크다운 문법으로 바꾸고, \newpage 삽입
    def replacer(match):
        image_filename = match.group(1)
        return f'![]({image_dir}/{image_filename})\n\n\\newpage\n'
    
    return re.sub(r'!\[\[([^\]]+)\]\]', replacer, md_text)

# ✅ 변환 대상만 순회
for filename in os.listdir(input_dir):
    if not filename.endswith(".md"):
        continue

    md_path = os.path.join(input_dir, filename)
    base_name = os.path.splitext(filename)[0]
    pdf_filename = base_name + ".pdf"
    pdf_path = os.path.join(output_dir, pdf_filename)

    # ✅ 이미 변환된 경우 건너뜀
    if os.path.exists(pdf_path):
        print(f"⏭️ 이미 변환됨: {pdf_filename}")
        continue

    print(f"🔄 변환 시작: {filename}")

    try:
        # 1. 마크다운 읽기
        with open(md_path, 'r', encoding='utf-8') as f:
            content = f.read()

        # 2. 이미지 링크 변환
        converted = convert_obsidian_image_links(content)

        # 3. 임시 파일 생성
        temp_md = os.path.join(output_dir, "__temp__.md")
        with open(temp_md, 'w', encoding='utf-8') as f:
            f.write(converted)

        # 4. 가로/세로 설정
        is_portrait = base_name in portrait_names
        orientation = 'portrait' if is_portrait else 'landscape'

        # 5. 변환
        pypandoc.convert_file(
            temp_md,
            to='pdf',
            outputfile=pdf_path,
            extra_args=[
                '--pdf-engine=xelatex',
                '-V', f'geometry:{orientation}',
                '-V', 'papersize=A4',
                '-V', 'geometry:margin=2cm',
                '-V', 'mainfont=맑은 고딕'   # ✅ 한글 폰트 설정 추가
            ]
        )

        print(f"✅ 변환 완료: {pdf_filename}")

    except Exception as e:
        print(f"❌ 오류 발생: {filename} → {e}")

# ✅ 임시파일 제거
try:
    os.remove(os.path.join(output_dir, "__temp__.md"))
except:
    pass

print("\n🎉 전체 PDF 변환 완료")