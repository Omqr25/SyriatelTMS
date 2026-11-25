import { useTranslation } from 'react-i18next';
import Badge from '../../../components/ui/Badge';

const TONES = {
  applicant: { new: 'blue', assigned: 'amber', interviewScheduled: 'amber', accepted: 'green', rejected: 'red' },
  interview: { scheduled: 'amber', completed: 'blue', accepted: 'green' },
  attendance: { absent: 'red' },
  vacation: { pending: 'amber', accepted: 'green', rejected: 'red' },
};

/** kind: applicant | interview | attendance | vacation */
export default function StatusBadge({ kind = 'applicant', status }) {
  const { t } = useTranslation();
  const label = kind === 'vacation'
    ? t(`vacation.${status}`, { ns: 'trainee' })
    : t(`status.${kind}.${status}`);
  return <Badge tone={TONES[kind]?.[status] ?? 'neutral'}>{label}</Badge>;
}
