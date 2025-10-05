# test_bb_rsi.py

from bb_rsi_utils import calculate_from_db

result = calculate_from_db('2025-01-06 12:45:00', 331.2)
result = calculate_from_db('2025-01-08 08:45:00', 331.7)
result = calculate_from_db('2025-07-11 08:45:00', 430.25)
result = calculate_from_db('2025-06-27 08:45:00', 417.5)
print(result)
# {'bb_center': ..., 'bb_upper': ..., 'bb_lower': ..., 'rsi': ...}
