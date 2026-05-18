# FLEXIcontent — Thai Translation Glossary

Pinned Thai equivalents for FLEXIcontent terminology. Every translator (human
or LLM) must consult this list before translating new strings so the user
experience stays consistent across 70+ INI files.

## Conventions

- **Brand and code identifiers stay in English** — never translate `FLEXIcontent`,
  `Joomla`, `URL`, `API`, `HTML`, `CSS`, `JSON`, `Ajax`, `CSV`, `PDF`, `SEF`.
- **Preserve placeholders verbatim** — `%s`, `%d`, `%1$s`, `%2$s`, `&nbsp;`,
  `<br/>`, `<b>...</b>`, `<a href="...">`, `<small>`, `::` separators in
  Joomla `LABEL::description` patterns.
- **No polite particles** in admin UI strings (ครับ / ค่ะ / นะคะ). The audience
  is site administrators. Use direct, neutral Thai.
- **Sentence case** for buttons and short labels; **noun phrases** without
  trailing punctuation unless the source ends in `.` or `:`.
- **Keep semicolon comments** (`;` lines) translated too if they're explanatory
  notes, but leave file-header comments (author / copyright / `$Id$`) untouched.

## Core terms

| English | Thai | Notes |
|---|---|---|
| FlexiContent | FLEXIcontent | brand — keep English casing |
| Content | เนื้อหา | generic noun |
| Item | บทความ | content unit; plural reads the same in Thai |
| Items | บทความ | no plural marker |
| Category | หมวดหมู่ | |
| Sub-category | หมวดหมู่ย่อย | |
| Type | ประเภท | content type |
| Content type | ประเภทเนื้อหา | |
| Field | ฟิลด์ | technical term; common in Joomla TH ecosystem |
| Tag | แท็ก | |
| Tags | แท็ก | |
| Title | ชื่อเรื่อง | for item titles; "หัวข้อ" for headings |
| Alias | นามแฝง | URL alias |
| Description | รายละเอียด | |
| Author | ผู้เขียน | |
| User | ผู้ใช้ | |
| User group | กลุ่มผู้ใช้ | |

## States

| English | Thai |
|---|---|
| State | สถานะ |
| Published | เผยแพร่ |
| Unpublished | ไม่เผยแพร่ |
| Draft | ฉบับร่าง |
| Pending / Pending Approval | รออนุมัติ |
| In progress | กำลังดำเนินการ |
| Archived | เก็บถาวร |
| Trashed | ถังขยะ |
| Deleted | ลบแล้ว |
| Featured | แนะนำ |
| Scheduled | กำหนดเวลา |
| Expired | หมดอายุ |

## Actions

| English | Thai |
|---|---|
| Add | เพิ่ม |
| New | สร้างใหม่ |
| Edit | แก้ไข |
| Save | บันทึก |
| Save & Close | บันทึกและปิด |
| Save & New | บันทึกและสร้างใหม่ |
| Save & Return | บันทึกและย้อนกลับ |
| Cancel | ยกเลิก |
| Delete | ลบ |
| Remove | ลบออก |
| Submit | ส่ง |
| Apply | ปรับใช้ |
| Reset | รีเซ็ต |
| Search | ค้นหา |
| Filter | กรอง |
| Refresh | รีเฟรช |
| Loading | กำลังโหลด |
| Upload | อัปโหลด |
| Download | ดาวน์โหลด |
| Preview | ดูตัวอย่าง |
| Print | พิมพ์ |
| Approve | อนุมัติ |
| Publish | เผยแพร่ |
| Unpublish | ยกเลิกการเผยแพร่ |

## Admin / config

| English | Thai |
|---|---|
| Configuration | การตั้งค่า |
| Options | ตัวเลือก |
| Settings | การตั้งค่า |
| Default | ค่าเริ่มต้น |
| Required | จำเป็น |
| Optional | ไม่จำเป็น |
| Enable | เปิดใช้งาน |
| Disable | ปิดใช้งาน |
| Yes | ใช่ |
| No | ไม่ใช่ |
| Access | สิทธิ์เข้าถึง |
| Permission | สิทธิ์ |
| Permissions | สิทธิ์ |
| Layout | เลย์เอาต์ |
| Template | เทมเพลต |
| Module | โมดูล |
| Plugin | ปลั๊กอิน |
| Component | คอมโพเนนต์ |
| Workflow | ขั้นตอนการอนุมัติ |
| Versioning | การจัดเก็บเวอร์ชัน |
| Version | เวอร์ชัน |
| Notification | การแจ้งเตือน |
| Note | หมายเหตุ |
| Warning | คำเตือน |
| Error | ข้อผิดพลาด |
| Success | สำเร็จ |
| Failed | ล้มเหลว |

## Filtering / list views

| English | Thai |
|---|---|
| All | ทั้งหมด |
| Any | อย่างใดอย่างหนึ่ง |
| None | ไม่มี |
| Select | เลือก |
| Please select | กรุณาเลือก |
| Total | รวมทั้งหมด |
| Results | ผลลัพธ์ |
| Order / Ordering | การเรียงลำดับ |
| Per page | ต่อหน้า |
| Hits | จำนวนการเข้าชม |
| Newest first | ใหม่สุดก่อน |
| Oldest first | เก่าสุดก่อน |
| Most popular | นิยมที่สุด |
| Alphabetical | เรียงตามตัวอักษร |
| Random | สุ่ม |

## Media / files

| English | Thai |
|---|---|
| Image | รูปภาพ |
| Images | รูปภาพ |
| File | ไฟล์ |
| Files | ไฟล์ |
| Media | สื่อ |
| Thumbnail / Thumb | รูปย่อ |
| Link | ลิงก์ |
| Links | ลิงก์ |
| URL | URL |
| Size | ขนาด |
| Display name | ชื่อที่แสดง |
| Extension | นามสกุลไฟล์ |

## Favourites / votes / comments

| English | Thai |
|---|---|
| Favourites | รายการโปรด |
| My favourites | รายการโปรดของฉัน |
| Vote | โหวต |
| Votes | โหวต |
| Rating | คะแนน |
| Comment | ความเห็น |
| Comments | ความเห็น |
| Review | รีวิว |
| Reviews | รีวิว |

## Time

| English | Thai |
|---|---|
| Created | สร้างเมื่อ |
| Modified | แก้ไขเมื่อ |
| Last modified | แก้ไขล่าสุด |
| Last updated | อัปเดตล่าสุด |
| Creation date | วันที่สร้าง |
| Date | วันที่ |
| Start | เริ่ม |
| Finish | สิ้นสุด |
| Immediately | ทันที |
| Never | ไม่มี |
| Hours / Days / Months / Years | ชั่วโมง / วัน / เดือน / ปี |

## Quality ratings (votes)

| English | Thai |
|---|---|
| Excellent | ดีเยี่ยม |
| Very good | ดีมาก |
| Good | ดี |
| Regular | ปานกลาง |
| Poor | แย่ |
| Very poor | แย่มาก |

## Strings to leave untranslated

- File header comments: `; $Id$`, `; author ...`, `; copyright ...`, `; license ...`,
  `; Note : All ini files need to be saved as UTF-8`
- Alphabetical-index strings with delimiters like
  `FLEXI_ALPHA_INDEX_CHARACTERS="a,b,c,d,..."` — these drive the A-Z navigator
  and must not be translated.
- `FLEXI_ICON_SEP=" "` — a single space separator
- `FLEXI_PAGETITLE_SEPARATOR="%1$s - %2$s"` — format spec, not text

## When in doubt

- Prefer Thai noun + English technical noun in parentheses on first use
  inside long help text (e.g. "ฟิลด์ (field) ที่ใช้กรอง").
- Match Joomla core Thai pack vocabulary where one exists (e.g. JNO/JYES).
- Keep translations short for buttons and tooltips; the UI grid is tight.
