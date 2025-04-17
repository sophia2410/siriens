import whisper
from moviepy.editor import VideoFileClip

# 비디오 파일에서 오디오 추출
video = VideoFileClip("input.mp4")
audio_path = "output.wav"
video.audio.write_audiofile(audio_path)

# Whisper 모델을 사용해 음성 인식
model = whisper.load_model("base")
result = model.transcribe(audio_path)

# 스크립트를 텍스트 파일로 저장
with open("transcript.txt", "w", encoding="utf-8") as f:
    f.write(result["text"])

print("스크립트 추출 완료!")

