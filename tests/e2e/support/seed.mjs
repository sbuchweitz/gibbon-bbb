// Test data in the demo Gibbon: personas from one class, and lessons relative to "now" (Gibbon runs in UTC).
import { q, setPassword, sql, sqlValue } from './gibbon.mjs';
import { PASSWORD } from './personas.mjs';

export function pickPersonas() {
  const [row] = sql(`SELECT t.gibbonCourseClassID, pt.username, ps.username, pp.username, ps.gibbonPersonID
    FROM gibbonCourseClassPerson t
    JOIN gibbonCourseClassPerson s ON (s.gibbonCourseClassID=t.gibbonCourseClassID AND s.role='Student')
    JOIN gibbonFamilyChild fc ON (fc.gibbonPersonID=s.gibbonPersonID)
    JOIN gibbonFamilyAdult fa ON (fa.gibbonFamilyID=fc.gibbonFamilyID AND fa.childDataAccess='Y')
    JOIN gibbonPerson pt ON (pt.gibbonPersonID=t.gibbonPersonID AND pt.status='Full' AND pt.gibbonRoleIDPrimary=2)
    JOIN gibbonPerson ps ON (ps.gibbonPersonID=s.gibbonPersonID AND ps.status='Full' AND ps.gibbonRoleIDPrimary=3)
    JOIN gibbonPerson pp ON (pp.gibbonPersonID=fa.gibbonPersonID AND pp.status='Full' AND pp.gibbonRoleIDPrimary=4)
    WHERE t.role='Teacher' ORDER BY t.gibbonCourseClassID LIMIT 1;`);
  if (!row) throw new Error('Demo data has no class with teacher + student + parent');
  const [classID, teacher, student, parent, studentID] = row;
  const outsider = sqlValue(`SELECT p.username FROM gibbonPerson p WHERE p.gibbonRoleIDPrimary=3 AND p.status='Full'
    AND p.gibbonPersonID NOT IN (SELECT gibbonPersonID FROM gibbonCourseClassPerson WHERE gibbonCourseClassID=${q(classID)}) LIMIT 1;`);
  for (const username of [teacher, student, parent, outsider]) setPassword(username, PASSWORD);

  return { classID, teacher, student, parent, outsider, studentID: Number(studentID) };
}

/** Creates a lesson by cloning a demo lesson row (gibbonPlannerEntry has many NOT NULL columns). */
export function createLesson(classID, name, date, timeStart, timeEnd, fields) {
  return Number(sqlValue(`CREATE TEMPORARY TABLE tmpLesson SELECT * FROM gibbonPlannerEntry ORDER BY gibbonPlannerEntryID LIMIT 1;
    UPDATE tmpLesson SET gibbonPlannerEntryID=NULL, gibbonCourseClassID=${q(classID)}, gibbonUnitID=NULL, name=${q(name)}, date=${q(date)},
      timeStart=${q(timeStart)}, timeEnd=${q(timeEnd)}, viewableStudents='Y', viewableParents='Y', fields=${q(JSON.stringify(fields))};
    INSERT INTO gibbonPlannerEntry SELECT * FROM tmpLesson;
    SELECT LAST_INSERT_ID();`));
}

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;
const hms = (d) => `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:00`;

export function seedLessons(classID) {
  const videoChatID = sqlValue("SELECT gibbonCustomFieldID FROM gibbonCustomField WHERE context='Lesson Plan' AND name='Video Chat'");
  const fields = { [videoChatID]: 'Include', videoLink: '' };
  const now = new Date();
  const start = new Date(now.getTime() - 5 * 60_000);
  const end = new Date(now.getTime() + 40 * 60_000);
  if (ymd(start) !== ymd(end)) throw new Error('Run the E2E suite away from midnight UTC (live lesson would span two days)');
  const day = (offset) => ymd(new Date(now.getTime() + offset * 86_400_000));

  return {
    videoChatFieldID: videoChatID,
    live: createLesson(classID, 'E2E Live Lesson', ymd(start), hms(start), hms(end), fields),
    past: createLesson(classID, 'E2E Past Lesson', day(-1), '09:00:00', '09:45:00', fields),
    future: createLesson(classID, 'E2E Future Lesson', day(1), '09:00:00', '09:45:00', fields),
    plain: createLesson(classID, 'E2E Lesson Without Video', ymd(start), hms(start), hms(end), { videoLink: '' }),
  };
}
