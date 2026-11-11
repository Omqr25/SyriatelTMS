import { Building2, MapPin, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../../components/ui/Button';
import Card from '../../../components/ui/Card';
import { Field, Input } from '../../../components/ui/Field';
import form from '../../../components/ui/Form.module.css';
import Modal from '../../../components/ui/Modal';
import PageHeader from '../../../components/ui/PageHeader';


import styles from './DepartmentsPage.module.css';
import useDepartments from '../../../hooks/HrHooks/useDepartments';
import Loading from "../../../components/ui/Loading";
import { toast } from 'react-toastify';
export default function DepartmentsPage() {
  const { t } = useTranslation('hr');

  const [showAdd, setShowAdd] = useState(false);

  const {Departments ,loading ,loadingCreate,createDepartments,refreshing}=useDepartments()

  const handleSubmit = async(e) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    try {
        const data= await  createDepartments(formData) 
        console.log("data",data)
        toast.success(data.message)
        setShowAdd(false);

    } catch (error) {
      const message =
      error.response?.data?.message ||
      error.message ||
      " Create department error";

    toast.error(
      Array.isArray(message)
        ? message.join("، ")
        : message
    );
    }
    // run('add', async () => {
    //   await addDepartment({ name: data.get('name'), location: data.get('location') });
    //   toast(t('departments.toast.added'));
    //   setShowAdd(false);
    // });
  };

  return loading ?(<Loading/>): (
    <>
      <PageHeader
        title={t('departments.listTitle')}
        update={refreshing}
        action={<Button icon={Plus} onClick={() => setShowAdd(true)}>{t('departments.add')}</Button>}
      />

      <div className={styles.grid}>
        {Departments.map((d) => (
          <Card key={d.id} className={styles.card}>
            <span className={styles.icon}><Building2 size={22} /></span>
            <h4>{d.name}</h4>
            <p className={styles.location}><MapPin size={14} /> {d.location}</p>
            <div className={styles.footer}>
              <span>{t('departments.supervisorsCount')}</span>
              <strong>{d.trainers_count}</strong>
            </div>
          </Card>
        ))}
      </div>

      {showAdd && (
        <Modal title={t('departments.add')} onClose={() => setShowAdd(false)}>
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t('departments.name')}><Input name="name" required /></Field>
            <Field label={t('departments.location')}><Input name="location" required /></Field>
            <Button type="submit" loading={loadingCreate}>{t('action.save', { ns: 'common' })}</Button>
          </form>
        </Modal>
      )}
    </>
  );
}
