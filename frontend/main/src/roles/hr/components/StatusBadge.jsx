import { useTranslation } from 'react-i18next';
import Badge from '../../../components/ui/Badge';

const TONES = {
  applicant: { new: 'blue', assigned: 'amber', interviewScheduled: 'amber', accepted: 'green', rejected: 'red' },
  interview: { scheduled: 'amber', completed: 'blue', accepted: 'green' },
  attendance: { absent: 'red' },
};

/** kind: applicant | interview | attendance */
export default function StatusBadge({ kind = 'applicant', status }) {
  const { t } = useTranslation();
  return <Badge tone={TONES[kind]?.[status] ?? 'neutral'}>{t(`status.${kind}.${status}`)}</Badge>;
}
