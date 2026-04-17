/**
 * Admin UI — Thai / English i18n
 * localStorage key: admin_lang  ('th' | 'en')  default: 'th'
 */
(function () {
    'use strict';

    // ─── Translation dictionary ───────────────────────────────────────────────
    const dict = {
        th: {
            /* Header */
            'header.hello'          : 'สวัสดี',
            'header.changePassword' : '🔑 เปลี่ยนรหัสผ่าน',
            'header.help'           : '📖 ช่วยเหลือ',
            'header.backToMain'     : '← กลับหน้าหลัก',
            'header.logout'         : 'ออกจากระบบ',

            /* Tabs */
            'tab.programs' : '🎵 Programs',
            'tab.events'   : '🎪 Events',
            'tab.requests' : '📝 Requests',
            'tab.credits'  : '✨ Credits',
            'tab.import'   : '📤 Import',
            'tab.artists'  : '🎤 Artists',
            'tab.users'    : '👤 Users',
            'tab.backup'   : '💾 Backup',
            'tab.settings' : '⚙️ Settings',
            'tab.contact'  : '✉️ Contact',

            /* Programs toolbar */
            'programs.allEvents'     : 'ทุก Events',
            'programs.search'        : 'ค้นหา...',
            'programs.allVenues'     : 'ทุกเวที',
            'programs.clearFilters'  : 'ล้างตัวกรอง',
            'programs.perPage'       : '/ หน้า',
            'programs.addProgram'    : '+ เพิ่ม Program',
            'programs.dateFrom'      : 'จากวันที่',
            'programs.dateTo'        : 'ถึงวันที่',

            /* Programs table headers */
            'th.title'       : 'ชื่อ',
            'th.dateTime'    : 'วันที่/เวลา',
            'th.venue'       : 'เวที',
            'th.artistGroup' : 'ศิลปิน / กลุ่ม',
            'th.type'        : 'ประเภท',
            'th.actions'     : 'จัดการ',

            /* Bulk bar */
            'bulk.selected'       : 'รายการที่เลือก',
            'bulk.selectAll'      : 'เลือกทั้งหมด',
            'bulk.clearAll'       : 'ยกเลิกทั้งหมด',
            'bulk.editSelected'   : '✏️ แก้ไขหลายรายการ',
            'bulk.deleteSelected' : '🗑️ ลบหลายรายการ',

            /* Requests */
            'req.allEvents'       : 'ทุก Events',
            'req.allStatuses'     : 'ทุกสถานะ',
            'req.pending'         : 'รอดำเนินการ',
            'req.approved'        : 'อนุมัติแล้ว',
            'req.rejected'        : 'ปฏิเสธแล้ว',
            'req.thType'          : 'ประเภท',
            'req.thProgram'       : 'ชื่อ Program',
            'req.thReporter'      : 'ผู้แจ้ง',
            'req.thDate'          : 'วันที่',
            'req.thStatus'        : 'สถานะ',
            'req.typeAdd'         : '➕ เพิ่ม',
            'req.typeModify'      : '✏️ แก้ไข',
            'req.typeAddFull'     : '➕ เพิ่ม Program ใหม่',
            'req.typeModifyFull'  : '✏️ แก้ไข Program ที่มีอยู่',
            'req.statusPending'   : 'รอ',
            'req.statusApproved'  : 'อนุมัติ',
            'req.statusRejected'  : 'ปฏิเสธ',
            'req.statusPendingFull'  : '🟡 รอดำเนินการ',
            'req.statusApprovedFull' : '✅ อนุมัติแล้ว',
            'req.statusRejectedFull' : '❌ ปฏิเสธแล้ว',
            'req.view'            : '👁️ ดู',
            'req.noRequests'      : 'ไม่มี requests',
            'req.detailTypeLabel' : 'ประเภท:',
            'req.detailStatusLabel': 'สถานะ:',

            /* Credits */
            'credits.search'       : 'ค้นหา credits...',
            'credits.allEvents'    : 'ทุก Events',
            'credits.addCredit'    : '+ เพิ่ม Credit',
            'credits.thTitle'      : 'ชื่อ',
            'credits.thLink'       : 'ลิงก์',
            'credits.thDesc'       : 'รายละเอียด',
            'credits.thOrder'      : 'ลำดับ',

            /* Events (conventions) */
            'events.search'        : 'ค้นหา events...',
            'events.addEvent'      : '+ เพิ่ม Event',
            'events.allStatuses'   : 'ทุกสถานะ',
            'events.active'        : 'Active',
            'events.inactive'      : 'Inactive',
            'events.allVenueModes' : 'ทุก Venue Mode',
            'events.dateFrom'      : 'จากวันที่',
            'events.dateTo'        : 'ถึงวันที่',
            'events.clearFilters'  : 'ล้างตัวกรอง',
            'events.perPage'       : '/ หน้า',
            'events.noData'        : 'ไม่พบ events',
            'events.thName'        : 'ชื่องาน',
            'events.thStartDate'   : 'วันเริ่ม',
            'events.thEndDate'     : 'วันสิ้นสุด',
            'events.thVenueMode'   : 'Venue Mode',
            'events.thActive'      : 'Active',
            'events.thPrograms'    : 'Programs',

            /* Import */
            'import.toEvent'        : '📦 Import ไปยัง Event:',
            'import.selectEvent'    : '-- เลือก Event --',
            'import.defaultType'    : '🏷️ Program Type (default):',
            'import.clickToUpload'  : 'คลิกเพื่ออัปโหลด',
            'import.dragDrop'       : 'หรือ ลากไฟล์มาวาง',
            'import.icsOnly'        : 'รองรับเฉพาะไฟล์ .ics (สูงสุด 5MB)',
            'import.uploading'      : 'กำลังอัปโหลด...',
            'import.previewTitle'   : '📋 ตัวอย่าง Programs',
            'import.statNew'        : 'ใหม่',
            'import.statDup'        : 'ซ้ำ',
            'import.statError'      : 'ผิดพลาด',
            'import.selectAll'      : 'เลือกทั้งหมด',
            'import.deselectAll'    : 'ยกเลิกทั้งหมด',
            'import.deleteSelected' : 'ลบที่เลือก',
            'import.cancel'         : 'ยกเลิก',
            'import.confirmBtn'     : '✅ ยืนยันการ Import',
            'import.thStatus'       : 'สถานะ',
            'import.thName'         : 'ชื่อ Program',
            'import.thDateTime'     : 'วันที่/เวลา',
            'import.thLocation'     : 'สถานที่',
            'import.thArtist'       : 'ศิลปินที่เกี่ยวข้อง',
            'import.thDuplicate'    : 'การจัดการซ้ำ',

            /* Import Summary */
            'summary.title'        : '📊 สรุปผลการ Import',
            'summary.inserted'     : 'เพิ่มใหม่:',
            'summary.updated'      : 'อัปเดต:',
            'summary.skipped'      : 'ข้าม:',
            'summary.errors'       : 'ผิดพลาด:',
            'summary.artistLinks'  : 'Artist links:',
            'summary.importNext'   : '📥 Import ไฟล์ถัดไป',
            'summary.viewPrograms' : 'ดู Programs ที่ Import แล้ว',

            /* Artists */
            'artists.search'          : 'ค้นหาศิลปิน...',
            'artists.filterAll'       : 'ทั้งหมด',
            'artists.filterGroup'     : 'กลุ่ม (Group)',
            'artists.filterSolo'      : 'บุคคล (Solo/Member)',
            'artists.addArtist'       : '+ เพิ่มศิลปิน',
            'artists.importMany'      : '📥 Import หลายคน',
            'artists.bulkAddGroup'    : '👥 เพิ่มเข้ากลุ่ม',
            'artists.bulkRemoveGroup' : '🚫 ถอดออกจากกลุ่ม',
            'artists.bulkCancel'      : '✕ ยกเลิก',
            'artists.thName'          : 'ชื่อ',
            'artists.thType'          : 'ประเภท',
            'artists.thGroup'         : 'กลุ่มที่สังกัด',
            'artists.thVariants'      : 'Variants',

            /* Users */
            'users.addUser'         : '+ เพิ่มผู้ใช้',
            'users.thUsername'      : 'Username',
            'users.thDisplayName'   : 'ชื่อที่แสดง',
            'users.thRole'          : 'Role',
            'users.thActive'        : 'Active',
            'users.thLastLogin'     : 'เข้าสู่ระบบล่าสุด',

            /* Backup */
            'backup.createBackup' : '💾 สร้าง Backup',
            'backup.uploadRestore': '📤 Upload & Restore',
            'backup.thFilename'   : 'Filename',
            'backup.thSize'       : 'ขนาด',
            'backup.thCreated'    : 'วันที่สร้าง',

            /* Settings */
            'settings.siteTitle'     : '📝 Site Title',
            'settings.siteTitleDesc' : 'ชื่อเว็บไซต์ที่แสดงใน browser tab, header และ ICS export',
            'settings.siteTheme'     : '🎨 Site Theme',
            'settings.siteThemeDesc' : 'เลือก theme สำหรับหน้าเว็บ public ทั้งหมด',
            'settings.saveTitle'     : '💾 บันทึก Title',
            'settings.saveTheme'     : '💾 บันทึก Theme',
            'settings.saved'         : '✅ บันทึกแล้ว',

            /* Settings Sub-tabs */
            'settings.subtab.site'   : '📝 Site',
            'settings.subtab.disclaimer': '⚠️ Disclaimer',
            'settings.subtab.telegram': '🤖 Telegram',
            'settings.subtab.contact': '✉️ Contact',
            'settings.subtab.users'  : '👤 Users',
            'settings.subtab.backup' : '💾 Backup',

            'settings.disclaimer'    : '⚠️ Disclaimer',
            'settings.disclaimerDesc': 'ข้อความ disclaimer ที่แสดงในหน้า "ติดต่อเรา" รองรับ 3 ภาษา',
            'settings.saveDisclaimer': '💾 บันทึก Disclaimer',

            'settings.telegram'      : '🤖 Telegram Notifications',
            'settings.telegramDesc'  : 'ตั้งค่า Telegram Bot สำหรับส่งการแจ้งเตือนผ่าน Telegram ก่อนเวลาเริ่มต้นของโปรแกรม',
            'settings.telegramBotToken': 'Bot Token',
            'settings.telegramBotTokenHint': 'Token จาก @BotFather (เก็บเป็นความลับ)',
            'settings.telegramBotUsername': 'Bot Username',
            'settings.telegramBotUsernameHint': 'ชื่อ bot ไม่มี @ (เช่น IdolStageBot)',
            'settings.telegramWebhookSecret': 'Webhook Secret',
            'settings.telegramWebhookSecretHint': 'Token สำหรับ webhook validation (auto-generate)',
            'settings.telegramGenerate': '🔄 สร้าง',
            'settings.telegramNotifyMinutes': 'แจ้งเตือนก่อน',
            'settings.telegramNotifyMinutesHint': 'เวลาก่อนโปรแกรมเริ่มที่จะส่งการแจ้งเตือน',
            'settings.telegramCronInterval': '📋 คำแนะนำ Cron',
            'settings.telegramCronIntervalHint': 'ตั้ง cron ให้รันตามคำแนะนำด้านล่าง — coverage ≥150%, window ±7.5 นาที',
            'settings.telegramCronPathHint': 'แทน /path/to/ ด้วย path จริงของระบบ',
            'settings.dailySummaryTime': '⏰ เวลาส่งสรุปรายวัน',
            'settings.dailySummaryTimeHint': 'ตั้งช่วงเวลาที่ส่งสรุปโปรแกรมทั้งวัน (เช่น 09:00-09:30 น.)',
            'settings.summaryStartTime': 'เวลาเริ่มต้น',
            'settings.summaryEndTime': 'เวลาสิ้นสุด',
            'settings.hour': 'ชั่วโมง (0-23)',
            'settings.minute': 'นาที (0-59)',
            'settings.dailySummaryTimeExample': '📌 ตัวอย่าง: 09:00-09:30 = ส่งสรุปรายวันระหว่าง 09:00-09:29 น. ทุกวัน',
            'settings.telegramEnabled': 'เปิดใช้งาน Telegram Notifications',
            'settings.telegramStatus': 'สถานะ Webhook',
            'settings.saveTelegram'  : '💾 บันทึก Telegram',
            'settings.telegramTest'  : '🧪 ทดสอบ Webhook',
            'settings.tested'        : '✅ ทดสอบสำเร็จ',
            'settings.telegramLogTitle'    : '📋 Activity Log',
            'settings.telegramLogRefresh'  : 'Refresh',
            'settings.telegramLogDownload' : 'Download',

            /* Contact */
            'contact.title'    : '✉️ ช่องทางติดต่อ',
            'contact.addChannel': '➕ เพิ่มช่องทาง',
            'contact.thIcon'   : 'Icon',
            'contact.thName'   : 'ชื่อ / รายละเอียด',
            'contact.thActive' : 'Active',

            /* Common */
            'common.loading'   : 'กำลังโหลด...',
            'common.cancel'    : 'ยกเลิก',
            'common.save'      : 'บันทึก',
            'common.delete'    : 'ลบ',
            'common.edit'      : 'แก้ไข',
            'common.confirm'   : 'ยืนยัน',
            'common.close'     : 'ปิด',
            'common.add'       : 'เพิ่ม',
            'common.search'    : 'ค้นหา',
            'common.actions'   : 'จัดการ',
            'common.yes'       : 'ใช่',
            'common.no'        : 'ไม่',
            'common.back'      : '← กลับ',
            'common.optional'  : 'ไม่บังคับ',
            'common.import'    : 'นำเข้า',
            'common.copy'      : 'คัดลอก',

            /* Pagination */
            'pagination.prev'   : '◀ ก่อนหน้า',
            'pagination.next'   : 'ถัดไป ▶',
            'pagination.page'   : 'หน้า',
            'pagination.total'  : 'รวม',

            /* Program Add/Edit Modal — form hints */
            'modal.artistGroupHint' : 'พิมพ์แล้วเลือกจาก dropdown หรือกด Enter / , เพื่อเพิ่ม · ศิลปินใหม่จะถูกสร้างอัตโนมัติ',
            'modal.programTypeHint' : 'เลือกจาก dropdown หรือพิมพ์ประเภทใหม่ได้',
            'modal.streamUrlHint'   : 'ลิงก์ IG Live, X Spaces, YouTube Live ฯลฯ (เว้นว่างได้ถ้าไม่มี)',

            /* Program Add/Edit Modal */
            'modal.addProgram'   : 'เพิ่ม Program',
            'modal.editProgram'  : 'แก้ไข Program',
            'modal.event'        : 'Event',
            'modal.noEvent'      : '-- ไม่ระบุ --',
            'modal.programName'  : 'ชื่อ Program *',
            'modal.organizer'    : 'Organizer',
            'modal.venue'        : 'เวที',
            'modal.startDate'    : 'วันที่เริ่ม *',
            'modal.startTime'    : 'เวลาเริ่ม *',
            'modal.endDate'      : 'วันที่สิ้นสุด *',
            'modal.endTime'      : 'เวลาสิ้นสุด *',
            'modal.artistGroup'  : 'Artist / Group',
            'modal.programType'  : 'ประเภท (Program Type)',
            'modal.streamUrl'    : '🔴 Live Stream URL',
            'modal.description'  : 'รายละเอียด',

            /* Delete Program Modal */
            'delete.confirmTitle'   : 'ยืนยันการลบ',
            'delete.programConfirm' : 'คุณต้องการลบ program นี้หรือไม่?',
            'delete.cannotUndo'     : 'การกระทำนี้ไม่สามารถย้อนกลับได้',
            'delete.deleteAll'      : 'ลบทั้งหมด',

            /* Bulk Edit Modal — form hints */
            'bulkEdit.venueHint'      : 'เลือกจาก dropdown หรือพิมพ์ชื่อเวทีใหม่',
            'bulkEdit.organizerHint'  : 'กรอกเพื่ออัปเดต organizer ของ programs ทั้งหมดที่เลือก',
            'bulkEdit.artistGroupHint': 'เพิ่ม tag เพื่ออัปเดต artist/group ของ programs ทั้งหมดที่เลือก · ว่าง = ไม่เปลี่ยนแปลง',
            'bulkEdit.programTypeHint': 'กรอกเพื่ออัปเดต program type ของ programs ทั้งหมดที่เลือก',

            /* Bulk Edit Modal */
            'bulkEdit.title'        : '✏️ แก้ไขหลายรายการ',
            'bulkEdit.editing'      : 'กำลังแก้ไข',
            'bulkEdit.programs'     : 'programs',
            'bulkEdit.venue'        : 'Venue (สถานที่)',
            'bulkEdit.organizer'    : 'Organizer (ผู้จัด)',
            'bulkEdit.artistGroup'  : 'Artist / Group',
            'bulkEdit.programType'  : 'Program Type (ประเภท)',
            'bulkEdit.warning'      : '⚠️ การแก้ไขจะเปลี่ยนค่าของ programs ทั้งหมดที่เลือกทันที',

            /* Credit Modal */
            'credit.addTitle'    : 'เพิ่ม Credit',
            'credit.editTitle'   : 'แก้ไข Credit',
            'credit.eventScope'  : '-- ทุก Event (Global) --',
            'credit.titleLabel'  : 'Title *',
            'credit.linkLabel'   : 'Link (URL)',
            'credit.descLabel'   : 'รายละเอียด',
            'credit.orderLabel'  : 'ลำดับการแสดง',
            'credit.eventLabel'  : 'Event',

            /* Event (convention) Modal — form hints */
            'event.emailHint'      : 'ใช้ใน ICS export — ORGANIZER;CN="ชื่องาน":mailto:email',

            /* Event (convention) Modal */
            'event.addTitle'       : 'เพิ่ม Event',
            'event.editTitle'      : 'แก้ไข Event',
            'event.useGlobalTheme' : '-- ใช้ Global Theme (จาก Settings) --',
            'event.enabled'        : 'เปิดใช้งาน',
            'event.nameLabel'      : 'Name *',
            'event.slugLabel'      : 'Slug *',
            'event.slugHint'       : 'ตัวอักษรพิมพ์เล็ก ตัวเลข และ - เท่านั้น',
            'event.emailLabel'     : 'Contact Email',
            'event.descLabel'      : 'รายละเอียด',
            'event.startDateLabel' : 'Start Date *',
            'event.endDateLabel'   : 'End Date *',
            'event.venueModeLabel' : 'Venue Mode',
            'event.activeLabel'    : 'Active',
            'event.themeLabel'     : 'Theme',
            'event.themeHint'      : 'ธีมเฉพาะ event นี้ — ถ้าไม่เลือก จะใช้ Global Theme จาก Settings (fallback: Dark)',
            'event.timezoneLabel'  : 'Timezone',
            'event.timezoneHint'   : 'เขตเวลาที่ใช้ในงาน — ใช้สำหรับ ICS export และแสดงผลบนหน้า event',

            /* Artist Modal — form hints */
            'artist.isGroupHint'      : 'เปิดเมื่อนี่คือกลุ่ม/วง; ปิดเมื่อนี่คือสมาชิก/ศิลปินเดี่ยว',
            'artist.groupOfHint'      : 'เลือกกลุ่มที่ศิลปินนี้เป็นสมาชิก (ว่าง = ศิลปินเดี่ยว หรือสมาชิกที่ยังไม่ได้ระบุกลุ่ม)',

            /* Artist Modal */
            'artist.addTitle'         : 'เพิ่มศิลปิน',
            'artist.editTitle'        : 'แก้ไขศิลปิน',
            'artist.nameLabel'        : 'ชื่อศิลปิน / กลุ่ม *',
            'artist.isGroup'          : 'เป็นกลุ่ม (Group)',
            'artist.groupOf'          : 'กลุ่มที่สังกัด',
            'artist.noGroup'          : '-- ไม่สังกัดกลุ่ม / ศิลปินเดี่ยว --',
            'artist.variantsCopyLabel': 'Variants ที่จะ copy',
            'artist.copySelectAll'    : 'เลือกทั้งหมด',
            'artist.copyClearAll'     : 'ยกเลิกทั้งหมด',

            /* Bulk Add to Group Modal */
            'bulkGroup.title'      : '👥 เพิ่มเข้ากลุ่ม',
            'bulkGroup.desc'       : 'เลือกกลุ่มที่ต้องการเพิ่มศิลปินที่เลือกทั้งหมดเข้า',
            'bulkGroup.targetLabel': 'กลุ่มปลายทาง *',
            'bulkGroup.selectOpt'  : '-- เลือกกลุ่ม --',
            'bulkGroup.note'       : 'หมายเหตุ: ศิลปินที่เป็น "กลุ่ม" จะถูกข้ามไป เฉพาะ "บุคคล/Solo" เท่านั้นที่จะถูกอัปเดต',

            /* Import Artists Modal */
            'importArtists.title'        : '📥 Import ศิลปินหลายคน',
            'importArtists.nameListLabel': 'รายชื่อศิลปิน',
            'importArtists.nameListHint' : '1 บรรทัด = 1 ศิลปิน',
            'importArtists.isGroup'      : 'เป็นกลุ่ม (Group)',
            'importArtists.isGroupHint'  : 'เปิดเมื่อรายชื่อทั้งหมดนี้คือกลุ่ม/วง',
            'importArtists.addToGroup'   : 'เพิ่มเข้ากลุ่ม',
            'importArtists.addToGroupOptional': '(ถ้าต้องการ)',
            'importArtists.noGroup'      : '-- ไม่สังกัดกลุ่ม --',
            'importArtists.groupSelectHint': 'เลือกกลุ่มที่ศิลปินที่ import จะสังกัด (ไม่บังคับ)',
            'importArtists.nameListSkip' : 'บรรทัดว่างและชื่อที่ซ้ำกันจะถูกข้ามอัตโนมัติ',

            /* Artist Copy Modal — form hints */
            'artist.copyVariantsHint'    : 'เลือก variants ที่ต้องการ copy ไปยังศิลปินใหม่ สามารถเพิ่ม/ลบเพิ่มเติมได้ภายหลัง',

            /* Artist Variants Modal */
            'variant.desc'        : 'Variant names คือชื่อสะกดอื่นๆ ของศิลปินนี้ (เช่น ตัวพิมพ์ใหญ่/เล็กต่างกัน หรือรูปแบบ "ชื่อ - กลุ่ม") ใช้สำหรับ auto-match ตอน ICS import',
            'variant.placeholder' : 'เพิ่ม variant name...',
            'variant.addBtn'      : '+ เพิ่ม',
            'variant.variantsBtn' : 'Variants',

            /* ICS Import — artist mapping hint */
            'import.artistMappingHint'   : 'กำหนด mapping ก่อน confirm import เพื่อให้ระบบสร้าง artist links อัตโนมัติ',

            /* Settings — Disclaimer labels */
            'settings.disclaimerTh' : '🇹🇭 ภาษาไทย',
            'settings.disclaimerEn' : '🇬🇧 English',
            'settings.disclaimerJa' : '🇯🇵 日本語',

            /* Backup Modal */
            'backup.restoreWarning' : '⚠️ การ Restore จะแทนที่ข้อมูลปัจจุบันทั้งหมด!',
            'backup.autoBackup'     : 'ระบบจะสร้าง auto-backup ก่อน restore อัตโนมัติ',
            'backup.restoreFrom'    : 'Restore จากไฟล์:',
            'backup.selectDb'       : 'เลือกไฟล์ .db',
            'backup.deleteTitle'    : 'ลบ Backup',
            'backup.deleteConfirm'  : 'ต้องการลบไฟล์ backup นี้?',

            /* Change Password */
            'cp.currentPassword'    : 'รหัสผ่านปัจจุบัน',
            'cp.newPassword'        : 'รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)',
            'cp.confirmPassword'    : 'ยืนยันรหัสผ่านใหม่',
            'cp.changeBtn'          : 'เปลี่ยนรหัสผ่าน',

            /* User Modal */
            'user.addTitle'         : 'เพิ่มผู้ใช้',
            'user.editTitle'        : 'แก้ไขผู้ใช้',
            'user.deleteTitle'      : 'ลบผู้ใช้',
            'user.deleteConfirm'    : 'คุณต้องการลบผู้ใช้นี้หรือไม่?',
            'user.passwordLabel'    : 'รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)',
            'user.passwordEditHint' : 'รหัสผ่าน (เว้นว่างไว้ถ้าไม่เปลี่ยน)',
            'user.active'           : 'เปิดใช้งาน',
            'user.roleAdmin'        : 'Admin - เข้าถึงทุกอย่าง',
            'user.roleAgent'        : 'Agent - จัดการ Programs เท่านั้น',
            'user.createBtn'        : 'สร้าง',
            'user.updateBtn'        : 'อัปเดต',

            /* Contact Channel Modal */
            'ch.addTitle'    : 'เพิ่มช่องทางติดต่อ',
            'ch.editTitle'   : 'แก้ไขช่องทางติดต่อ',
            'ch.icon'        : 'Icon (emoji)',
            'ch.title'       : 'ชื่อช่องทาง',
            'ch.description' : 'รายละเอียด',
            'ch.url'         : 'URL / Contact',
            'ch.order'       : 'ลำดับการแสดง',
            'ch.active'      : 'แสดงในหน้าติดต่อเรา (Active)',

            /* Request Detail */
            'req.detailTitle'        : '📋 รายละเอียดคำขอ',
            'req.approve'            : '✅ อนุมัติ',
            'req.reject'             : '❌ ปฏิเสธ',
            'req.adminNote'          : 'หมายเหตุจาก Admin',
            'req.adminNotePlaceholder': 'ระบุหมายเหตุสำหรับผู้แจ้ง (ไม่บังคับ)',

            /* Login page */
            'login.title'      : 'Admin Login',
            'login.subtitle'   : 'กรุณาเข้าสู่ระบบ',
            'login.username'   : 'Username',
            'login.password'   : 'Password',
            'login.submit'     : 'เข้าสู่ระบบ',
            'login.backToMain' : '← กลับหน้าหลัก',
            'login.errInvalid' : 'Username หรือ Password ไม่ถูกต้อง',
            'login.errTooMany' : 'พยายาม login หลายครั้งเกินไป กรุณารอ {min} นาทีแล้วลองใหม่',
        },

        en: {
            /* Header */
            'header.hello'          : 'Hello',
            'header.changePassword' : '🔑 Change Password',
            'header.help'           : '📖 Help',
            'header.backToMain'     : '← Back to main',
            'header.logout'         : 'Logout',

            /* Tabs */
            'tab.programs' : '🎵 Programs',
            'tab.events'   : '🎪 Events',
            'tab.requests' : '📝 Requests',
            'tab.credits'  : '✨ Credits',
            'tab.import'   : '📤 Import',
            'tab.artists'  : '🎤 Artists',
            'tab.users'    : '👤 Users',
            'tab.backup'   : '💾 Backup',
            'tab.settings' : '⚙️ Settings',
            'tab.contact'  : '✉️ Contact',

            /* Programs toolbar */
            'programs.allEvents'    : 'All Events',
            'programs.search'       : 'Search...',
            'programs.allVenues'    : 'All Venues',
            'programs.clearFilters' : 'Clear Filters',
            'programs.perPage'      : '/ page',
            'programs.addProgram'   : '+ Add Program',
            'programs.dateFrom'     : 'From date',
            'programs.dateTo'       : 'To date',

            /* Programs table headers */
            'th.title'       : 'Title',
            'th.dateTime'    : 'Date/Time',
            'th.venue'       : 'Venue',
            'th.artistGroup' : 'Artist / Group',
            'th.type'        : 'Type',
            'th.actions'     : 'Actions',

            /* Bulk bar */
            'bulk.selected'       : 'selected',
            'bulk.selectAll'      : 'Select all',
            'bulk.clearAll'       : 'Clear all',
            'bulk.editSelected'   : '✏️ Edit selected',
            'bulk.deleteSelected' : '🗑️ Delete selected',

            /* Requests */
            'req.allEvents'       : 'All Events',
            'req.allStatuses'     : 'All Statuses',
            'req.pending'         : 'Pending',
            'req.approved'        : 'Approved',
            'req.rejected'        : 'Rejected',
            'req.thType'          : 'Type',
            'req.thProgram'       : 'Program',
            'req.thReporter'      : 'Reporter',
            'req.thDate'          : 'Date',
            'req.thStatus'        : 'Status',
            'req.typeAdd'         : '➕ Add',
            'req.typeModify'      : '✏️ Modify',
            'req.typeAddFull'     : '➕ Add New Program',
            'req.typeModifyFull'  : '✏️ Edit Existing Program',
            'req.statusPending'   : 'Pending',
            'req.statusApproved'  : 'Approved',
            'req.statusRejected'  : 'Rejected',
            'req.statusPendingFull'  : '🟡 Pending',
            'req.statusApprovedFull' : '✅ Approved',
            'req.statusRejectedFull' : '❌ Rejected',
            'req.view'            : '👁️ View',
            'req.noRequests'      : 'No requests',
            'req.detailTypeLabel' : 'Type:',
            'req.detailStatusLabel': 'Status:',

            /* Credits */
            'credits.search'    : 'Search credits...',
            'credits.allEvents' : 'All Events',
            'credits.addCredit' : '+ Add Credit',
            'credits.thTitle'   : 'Title',
            'credits.thLink'    : 'Link',
            'credits.thDesc'    : 'Description',
            'credits.thOrder'   : 'Order',

            /* Events (conventions) */
            'events.search'        : 'Search events...',
            'events.addEvent'      : '+ Add Event',
            'events.allStatuses'   : 'All Statuses',
            'events.active'        : 'Active',
            'events.inactive'      : 'Inactive',
            'events.allVenueModes' : 'All Venue Modes',
            'events.dateFrom'      : 'From date',
            'events.dateTo'        : 'To date',
            'events.clearFilters'  : 'Clear Filters',
            'events.perPage'       : '/ page',
            'events.noData'        : 'No events found',
            'events.thName'        : 'Name',
            'events.thStartDate'   : 'Start Date',
            'events.thEndDate'     : 'End Date',
            'events.thVenueMode'   : 'Venue Mode',
            'events.thActive'      : 'Active',
            'events.thPrograms'    : 'Programs',

            /* Import */
            'import.toEvent'        : '📦 Import to Event:',
            'import.selectEvent'    : '-- Select Event --',
            'import.defaultType'    : '🏷️ Default Program Type:',
            'import.clickToUpload'  : 'Click to upload',
            'import.dragDrop'       : 'or drag and drop',
            'import.icsOnly'        : '.ics files only (max 5MB)',
            'import.uploading'      : 'Uploading...',
            'import.previewTitle'   : '📋 Preview Programs',
            'import.statNew'        : 'New',
            'import.statDup'        : 'Duplicate',
            'import.statError'      : 'Error',
            'import.selectAll'      : 'Select all',
            'import.deselectAll'    : 'Deselect all',
            'import.deleteSelected' : 'Delete selected',
            'import.cancel'         : 'Cancel',
            'import.confirmBtn'     : '✅ Confirm Import',
            'import.thStatus'       : 'Status',
            'import.thName'         : 'Program Name',
            'import.thDateTime'     : 'Date/Time',
            'import.thLocation'     : 'Location',
            'import.thArtist'       : 'Artist/Group',
            'import.thDuplicate'    : 'Duplicate Handling',

            /* Import Summary */
            'summary.title'        : '📊 Import Summary',
            'summary.inserted'     : 'Added:',
            'summary.updated'      : 'Updated:',
            'summary.skipped'      : 'Skipped:',
            'summary.errors'       : 'Errors:',
            'summary.artistLinks'  : 'Artist links:',
            'summary.importNext'   : '📥 Import Next File',
            'summary.viewPrograms' : 'View Imported Programs',

            /* Artists */
            'artists.search'          : 'Search artists...',
            'artists.filterAll'       : 'All',
            'artists.filterGroup'     : 'Group',
            'artists.filterSolo'      : 'Solo / Member',
            'artists.addArtist'       : '+ Add Artist',
            'artists.importMany'      : '📥 Import multiple',
            'artists.bulkAddGroup'    : '👥 Add to group',
            'artists.bulkRemoveGroup' : '🚫 Remove from group',
            'artists.bulkCancel'      : '✕ Cancel',
            'artists.thName'          : 'Name',
            'artists.thType'          : 'Type',
            'artists.thGroup'         : 'Group',
            'artists.thVariants'      : 'Variants',

            /* Users */
            'users.addUser'       : '+ Add User',
            'users.thUsername'    : 'Username',
            'users.thDisplayName' : 'Display Name',
            'users.thRole'        : 'Role',
            'users.thActive'      : 'Active',
            'users.thLastLogin'   : 'Last Login',

            /* Backup */
            'backup.createBackup'  : '💾 Create Backup',
            'backup.uploadRestore' : '📤 Upload & Restore',
            'backup.thFilename'    : 'Filename',
            'backup.thSize'        : 'Size',
            'backup.thCreated'     : 'Created',

            /* Settings */
            'settings.siteTitle'     : '📝 Site Title',
            'settings.siteTitleDesc' : 'Site name shown in browser tab, header, and ICS export',
            'settings.siteTheme'     : '🎨 Site Theme',
            'settings.siteThemeDesc' : 'Choose theme for all public pages',
            'settings.saveTitle'     : '💾 Save Title',
            'settings.saveTheme'     : '💾 Save Theme',
            'settings.saved'         : '✅ Saved',

            /* Settings Sub-tabs */
            'settings.subtab.site'   : '📝 Site',
            'settings.subtab.disclaimer': '⚠️ Disclaimer',
            'settings.subtab.telegram': '🤖 Telegram',
            'settings.subtab.contact': '✉️ Contact',
            'settings.subtab.users'  : '👤 Users',
            'settings.subtab.backup' : '💾 Backup',

            'settings.disclaimer'    : '⚠️ Disclaimer',
            'settings.disclaimerDesc': 'Disclaimer text on the "Contact" page (3 languages)',
            'settings.saveDisclaimer': '💾 Save Disclaimer',

            'settings.telegram'      : '🤖 Telegram Notifications',
            'settings.telegramDesc'  : 'Configure Telegram Bot to send notifications before upcoming programs',
            'settings.telegramBotToken': 'Bot Token',
            'settings.telegramBotTokenHint': 'Token from @BotFather (keep secret)',
            'settings.telegramBotUsername': 'Bot Username',
            'settings.telegramBotUsernameHint': 'Bot name without @ (e.g. IdolStageBot)',
            'settings.telegramWebhookSecret': 'Webhook Secret',
            'settings.telegramWebhookSecretHint': 'Token for webhook validation (auto-generate)',
            'settings.telegramGenerate': '🔄 Generate',
            'settings.telegramNotifyMinutes': 'Notify Before',
            'settings.telegramNotifyMinutesHint': 'Time before program starts to send notification',
            'settings.telegramCronInterval': '📋 Cron Recommendation',
            'settings.telegramCronIntervalHint': 'Run cron as recommended below — coverage ≥150%, window ±7.5 min',
            'settings.telegramCronPathHint': 'Replace /path/to/ with the actual path on your server',
            'settings.dailySummaryTime': '⏰ Daily Summary Time',
            'settings.dailySummaryTimeHint': 'Set time window for sending daily program summary (e.g. 09:00-09:30)',
            'settings.summaryStartTime': 'Start Time',
            'settings.summaryEndTime': 'End Time',
            'settings.hour': 'Hour (0-23)',
            'settings.minute': 'Minute (0-59)',
            'settings.dailySummaryTimeExample': '📌 Example: 09:00-09:30 = Send daily summary between 09:00-09:29 every day',
            'settings.telegramEnabled': 'Enable Telegram Notifications',
            'settings.telegramStatus': 'Webhook Status',
            'settings.saveTelegram'  : '💾 Save Telegram',
            'settings.telegramTest'  : '🧪 Test Webhook',
            'settings.tested'        : '✅ Test Successful',
            'settings.telegramLogTitle'    : '📋 Activity Log',
            'settings.telegramLogRefresh'  : 'Refresh',
            'settings.telegramLogDownload' : 'Download',

            /* Contact */
            'contact.title'     : '✉️ Contact Channels',
            'contact.addChannel': '➕ Add Channel',
            'contact.thIcon'    : 'Icon',
            'contact.thName'    : 'Name / Description',
            'contact.thActive'  : 'Active',

            /* Common */
            'common.loading'   : 'Loading...',
            'common.cancel'    : 'Cancel',
            'common.save'      : 'Save',
            'common.delete'    : 'Delete',
            'common.edit'      : 'Edit',
            'common.confirm'   : 'Confirm',
            'common.close'     : 'Close',
            'common.add'       : 'Add',
            'common.search'    : 'Search',
            'common.actions'   : 'Actions',
            'common.yes'       : 'Yes',
            'common.no'        : 'No',
            'common.back'      : '← Back',
            'common.optional'  : 'optional',
            'common.import'    : 'Import',
            'common.copy'      : 'Copy',

            /* Pagination */
            'pagination.prev'   : '◀ Previous',
            'pagination.next'   : 'Next ▶',
            'pagination.page'   : 'Page',
            'pagination.total'  : 'Total',

            /* Program Add/Edit Modal */
            'modal.addProgram'   : 'Add Program',
            'modal.editProgram'  : 'Edit Program',
            'modal.event'        : 'Event',
            'modal.noEvent'      : '-- None --',
            'modal.programName'  : 'Program Name *',
            'modal.organizer'    : 'Organizer',
            'modal.venue'        : 'Venue',
            'modal.startDate'    : 'Start Date *',
            'modal.startTime'    : 'Start Time *',
            'modal.endDate'      : 'End Date *',
            'modal.endTime'      : 'End Time *',
            'modal.artistGroup'  : 'Artist / Group',
            'modal.programType'  : 'Program Type',
            'modal.streamUrl'    : '🔴 Live Stream URL',
            'modal.description'  : 'Description',

            /* Delete Program Modal */
            'delete.confirmTitle'   : 'Confirm Delete',
            'delete.programConfirm' : 'Delete this program?',
            'delete.cannotUndo'     : 'This action cannot be undone.',
            'delete.deleteAll'      : 'Delete all',

            /* Bulk Edit Modal */
            'bulkEdit.title'       : '✏️ Edit Multiple',
            'bulkEdit.editing'     : 'Editing',
            'bulkEdit.programs'    : 'programs',
            'bulkEdit.venue'       : 'Venue',
            'bulkEdit.organizer'   : 'Organizer',
            'bulkEdit.artistGroup' : 'Artist / Group',
            'bulkEdit.programType' : 'Program Type',
            'bulkEdit.warning'     : '⚠️ Changes will be applied to all selected programs immediately.',

            /* Credit Modal */
            'credit.addTitle'    : 'Add Credit',
            'credit.editTitle'   : 'Edit Credit',
            'credit.eventScope'  : '-- All Events (Global) --',
            'credit.titleLabel'  : 'Title *',
            'credit.linkLabel'   : 'Link (URL)',
            'credit.descLabel'   : 'Description',
            'credit.orderLabel'  : 'Display Order',
            'credit.eventLabel'  : 'Event',

            /* Event (convention) Modal */
            'event.addTitle'       : 'Add Event',
            'event.editTitle'      : 'Edit Event',
            'event.useGlobalTheme' : '-- Use Global Theme (from Settings) --',
            'event.enabled'        : 'Enabled',
            'event.nameLabel'      : 'Name *',
            'event.slugLabel'      : 'Slug *',
            'event.slugHint'       : 'Lowercase letters, numbers, and - only',
            'event.emailLabel'     : 'Contact Email',
            'event.descLabel'      : 'Description',
            'event.startDateLabel' : 'Start Date *',
            'event.endDateLabel'   : 'End Date *',
            'event.venueModeLabel' : 'Venue Mode',
            'event.activeLabel'    : 'Active',
            'event.themeLabel'     : 'Theme',
            'event.themeHint'      : 'Event-specific theme — if not set, uses Global Theme from Settings (fallback: Dark)',
            'event.timezoneLabel'  : 'Timezone',
            'event.timezoneHint'   : 'Timezone for this event — used for ICS export and display on event page',

            /* Artist Modal — form hints */
            'artist.isGroupHint'      : 'Enable when this is a group/band; disable when this is a member/solo artist',
            'artist.groupOfHint'      : 'Select the group this artist belongs to (empty = solo or unassigned member)',

            /* Import Artists Modal — form hints */
            'importArtists.isGroupHint'   : 'Enable when all names in the list are groups/bands',
            'importArtists.groupSelectHint': 'Select the group these imported artists will belong to (optional)',
            'importArtists.nameListSkip'  : 'Empty lines and duplicate names will be skipped automatically',

            /* Artist Copy Modal — form hints */
            'artist.copyVariantsHint'     : 'Select variants to copy to the new artist — you can add/remove more later',

            /* Artist Variants Modal */
            'variant.desc'        : 'Variant names are alternative spellings of this artist\'s name (e.g. different capitalisation or "Name - Group" format) — used for auto-matching during ICS import',
            'variant.placeholder' : 'Add variant name...',
            'variant.addBtn'      : '+ Add',
            'variant.variantsBtn' : 'Variants',

            /* ICS Import — artist mapping hint */
            'import.artistMappingHint'    : 'Set mapping before confirming import so the system can create artist links automatically',

            /* Program Add/Edit Modal — form hints */
            'modal.artistGroupHint' : 'Type then select from dropdown or press Enter / , to add · New artists will be created automatically',
            'modal.programTypeHint' : 'Select from dropdown or type a new type',
            'modal.streamUrlHint'   : 'IG Live, X Spaces, YouTube Live URL etc. (leave blank if none)',

            /* Bulk Edit Modal — form hints */
            'bulkEdit.venueHint'      : 'Select from dropdown or type a new venue name',
            'bulkEdit.organizerHint'  : 'Fill in to update the organizer of all selected programs',
            'bulkEdit.artistGroupHint': 'Add tags to update artist/group of all selected programs · Empty = no change',
            'bulkEdit.programTypeHint': 'Fill in to update the program type of all selected programs',

            /* Event (convention) Modal — form hints */
            'event.emailHint'      : 'Used in ICS export — ORGANIZER;CN="Event Name":mailto:email',

            /* Artist Modal */
            'artist.addTitle'         : 'Add Artist',
            'artist.editTitle'        : 'Edit Artist',
            'artist.nameLabel'        : 'Artist / Group Name *',
            'artist.isGroup'          : 'Is Group',
            'artist.groupOf'          : 'Member of Group',
            'artist.noGroup'          : '-- No group / Solo artist --',
            'artist.variantsCopyLabel': 'Variants to copy',
            'artist.copySelectAll'    : 'Select all',
            'artist.copyClearAll'     : 'Clear all',

            /* Bulk Add to Group Modal */
            'bulkGroup.title'      : '👥 Add to Group',
            'bulkGroup.desc'       : 'Select the group to add all selected artists to',
            'bulkGroup.targetLabel': 'Target Group *',
            'bulkGroup.selectOpt'  : '-- Select Group --',
            'bulkGroup.note'       : 'Note: Artists marked as "Group" will be skipped. Only "Solo / Members" will be updated.',

            /* Import Artists Modal */
            'importArtists.title'        : '📥 Import Multiple Artists',
            'importArtists.nameListLabel': 'Artist Names',
            'importArtists.nameListHint' : '1 line = 1 artist',
            'importArtists.isGroup'      : 'Is Group',
            'importArtists.addToGroup'   : 'Add to Group',
            'importArtists.addToGroupOptional': '(optional)',
            'importArtists.noGroup'      : '-- No group --',

            /* Settings — Disclaimer labels */
            'settings.disclaimerTh' : '🇹🇭 Thai',
            'settings.disclaimerEn' : '🇬🇧 English',
            'settings.disclaimerJa' : '🇯🇵 Japanese',

            /* Backup Modal */
            'backup.restoreWarning' : '⚠️ Restore will replace all current data!',
            'backup.autoBackup'     : 'An auto-backup will be created before restore.',
            'backup.restoreFrom'    : 'Restore from file:',
            'backup.selectDb'       : 'Select .db file',
            'backup.deleteTitle'    : 'Delete Backup',
            'backup.deleteConfirm'  : 'Delete this backup file?',

            /* Change Password */
            'cp.currentPassword'    : 'Current Password',
            'cp.newPassword'        : 'New Password (min 8 characters)',
            'cp.confirmPassword'    : 'Confirm New Password',
            'cp.changeBtn'          : 'Change Password',

            /* User Modal */
            'user.addTitle'         : 'Add User',
            'user.editTitle'        : 'Edit User',
            'user.deleteTitle'      : 'Delete User',
            'user.deleteConfirm'    : 'Are you sure you want to delete this user?',
            'user.passwordLabel'    : 'Password (min 8 characters)',
            'user.passwordEditHint' : 'Password (leave blank to keep current)',
            'user.active'           : 'Active',
            'user.roleAdmin'        : 'Admin - Full access',
            'user.roleAgent'        : 'Agent - Programs management only',
            'user.createBtn'        : 'Create',
            'user.updateBtn'        : 'Update',

            /* Contact Channel Modal */
            'ch.addTitle'    : 'Add Contact Channel',
            'ch.editTitle'   : 'Edit Contact Channel',
            'ch.icon'        : 'Icon (emoji)',
            'ch.title'       : 'Channel Name',
            'ch.description' : 'Description',
            'ch.url'         : 'URL / Contact',
            'ch.order'       : 'Display Order',
            'ch.active'      : 'Show on Contact page (Active)',

            /* Request Detail */
            'req.detailTitle'        : '📋 Request Details',
            'req.approve'            : '✅ Approve',
            'req.reject'             : '❌ Reject',
            'req.adminNote'          : 'Admin Note',
            'req.adminNotePlaceholder': 'Optional note for the requester',

            /* Login page */
            'login.title'      : 'Admin Login',
            'login.subtitle'   : 'Please sign in to continue',
            'login.username'   : 'Username',
            'login.password'   : 'Password',
            'login.submit'     : 'Login',
            'login.backToMain' : '← Back to main',
            'login.errInvalid' : 'Invalid username or password',
            'login.errTooMany' : 'Too many login attempts. Please wait {min} minutes and try again.',
        }
    };

    // ─── Core functions ───────────────────────────────────────────────────────

    /** Current language ('th' | 'en') */
    function getLang() {
        return localStorage.getItem('admin_lang') || 'th';
    }

    /** Translate key; falls back to TH then the key itself */
    function adminT(key) {
        var lang = getLang();
        return (dict[lang] && dict[lang][key]) || (dict['th'] && dict['th'][key]) || key;
    }

    /** Apply all [data-i18n] / [data-i18n-placeholder] / [data-i18n-title] in the document */
    function applyAdminTranslations() {
        var lang = getLang();

        /* text content */
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var val = (dict[lang] && dict[lang][key]) || (dict['th'] && dict['th'][key]);
            if (val !== undefined) el.textContent = val;
        });

        /* placeholder */
        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            var key = el.getAttribute('data-i18n-placeholder');
            var val = (dict[lang] && dict[lang][key]) || (dict['th'] && dict['th'][key]);
            if (val !== undefined) el.placeholder = val;
        });

        /* title attribute */
        document.querySelectorAll('[data-i18n-title]').forEach(function (el) {
            var key = el.getAttribute('data-i18n-title');
            var val = (dict[lang] && dict[lang][key]) || (dict['th'] && dict['th'][key]);
            if (val !== undefined) el.title = val;
        });

        /* option value text inside selects */
        document.querySelectorAll('option[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var val = (dict[lang] && dict[lang][key]) || (dict['th'] && dict['th'][key]);
            if (val !== undefined) el.textContent = val;
        });

        /* Update active language button */
        document.querySelectorAll('.admin-lang-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
    }

    /** Switch language and re-render */
    function changeAdminLang(lang) {
        if (lang !== 'th' && lang !== 'en') return;
        localStorage.setItem('admin_lang', lang);
        applyAdminTranslations();
        /* Dispatch event so any other JS can react */
        document.dispatchEvent(new CustomEvent('adminLangChange', { detail: { lang: lang } }));
    }

    // ─── Expose globally ──────────────────────────────────────────────────────
    window.adminT               = adminT;
    window.getLang              = getLang;
    window.changeAdminLang      = changeAdminLang;
    window.applyAdminTranslations = applyAdminTranslations;

    // Apply on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyAdminTranslations);
    } else {
        applyAdminTranslations();
    }
})();
