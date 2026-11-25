import Card from "../../../../components/ui/Card.jsx";
import StatusBadge from "../../../hr/components/StatusBadge.jsx";
import Pagination from "../../../../components/ui/Pagination";
import Button from "../../../../components/ui/Button.jsx"
import Modal from "../../../../components/ui/Modal.jsx"
import styles from "./VacationPage.module.css";
import Loading from "../../../../components/ui/Loading";
import {Field,Input} from "../../../../components/ui/Field.jsx"
import { ArrowRight, CalendarDays } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useEffect } from "react";
import VacationHooks from "../../../../hooks/TraineeHooks/vacationHooks.js";


export default function VacationPage() {

  const { t, i18n } = useTranslation("trainee");
  const submittedDate = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "Asia/Damascus",
  });
    const {
        vacationId,
        vacationRequest,
        sendingVacationRequest,
        updatingVacationRequest,
        vacationsList,
        vacationState,
        cancelVacationState,
        loadingCancelVacationState,
        loadingAction,
        getVacationsList,
        extraData,
        paging,
        openCancelVacation,
        openUpdateVacation,
        openAddVacation,
        sendCancelVacation,
        sendUpdateVacation,
        sendVacationRequest,
        setAddVacation,
        setCancelVacationState, 
        setVacationRequest
    } = VacationHooks()

  useEffect(() => {
    getVacationsList();
  }, [getVacationsList]);

    const sendActionBasedOnData = (e) => {
      e.preventDefault();
      if (vacationId.current === null) {
          sendVacationRequest();
      } else {
          sendUpdateVacation();
      }
    }

  const cancelVacationModel = <Modal
                  title={t("vacation.areYouSure")}
                  onClose={()=>setCancelVacationState(false)}
                    >
                  <div className= {styles.cancelVacation}>
                    <Button 
                        variant="soft"
                        loading={loadingCancelVacationState}
                        disabled={loadingCancelVacationState}
                        children={t("vacation.cancelRequest")}
                        onClick={()=>sendCancelVacation()}
                  />
                  <Button
                        disabled={loadingCancelVacationState}
                        children={t("vacation.cancel")}
                        onClick={()=>setCancelVacationState(false)}
                  />
                  </div>
  </Modal>
  const addVacationFormComponent = 
          <Modal 
          title={t("vacation.vacationRequest")}
          onClose={()=>setAddVacation(false)}>
            <form onSubmit={(e) => sendActionBasedOnData(e)}>
              
                <Field key={"reason"}
                label={t("evaluation.reason")}
                children={<Input 
                required
                value={vacationRequest.reason}
                onChange={(e)=>setVacationRequest((pre)=>({...pre,"reason":e.target.value}))}
                
                />}/>
                <div className={styles.vacationDate}>
                    <Field key={"startDate"}
                        label={t("vacation.startDate")}
                        children={<Input type="date" required min={extraData?.earliest_leave_date} max={extraData?.latest_leave_date}
                        value={vacationRequest.start_date} 
                        onChange={(e)=>setVacationRequest((pre)=>({...pre,"start_date":e.target.value}))} />}/>
                    <Field key={"endDate"}
                        label={t("vacation.endDate")} 
                        children={
                        <Input type="date" required min={vacationRequest.start_date || extraData?.earliest_leave_date} max={extraData?.latest_leave_date}
                        value={vacationRequest.end_date}
                        onChange={(e)=>setVacationRequest((pre)=>({...pre,"end_date":e.target.value}))}/>}/>
                </div>
                  <br/>
                  <Button 
                        type="submit"
                        className={styles.sendRequest}
                        loading={sendingVacationRequest ||updatingVacationRequest}
                        disabled={sendingVacationRequest||updatingVacationRequest}
                        children={t("evaluation.send")}
                  />
              </form>
          </Modal>
  return (
    loadingAction ?<Loading />:
  <>
    <section className={styles.page}>
      <header>
        <span className={styles.count}>
          {paging.total} {t("vacation.requests")}
        </span>
      </header>
      <div className={styles.list}>
        {vacationsList.map((vacation) => (
          <Card
            key={vacation.id }
            className={`${styles.card} ${styles[vacation.status] ?? ""}`}
          >
            <div className={styles.cardHeader}>
              <div className={styles.reasonGroup}>
                <h3 className={styles.reason}>
                  {vacation.reason}
                </h3>
              </div>
              <StatusBadge kind="vacation" status={vacation.status} />
            </div>

            <div className={styles.dateSection}>
              <div className={styles.dateBlock}>
                <span className={styles.dateLabel}>
                  {t("vacation.startDate")}
                </span>

                <span className={styles.dateValue}>
                  {vacation.start_date}
                </span>
              </div>

              <div className={styles.arrow}>
                <ArrowRight size={18} strokeWidth={1.8} />
              </div>

              <div className={styles.dateBlock}>
                <span className={styles.dateLabel}>
                  {t("vacation.endDate")}
                </span>

                <span className={styles.dateValue}>
                  {vacation.end_date}
                </span>
              </div>
            </div>
            <div className={styles.footer}>
              <span className={styles.meta}>
                <CalendarDays size={15} />
                {t("vacation.submitted")} {vacation.submitted_at && submittedDate.format(new Date(vacation.submitted_at))}
              </span>
              {(vacation.can_edit || vacation.can_cancel) && <div className={styles.container}>
                {vacation.can_cancel && <Button
                        variant="soft"
                        className={styles.sendRequest}
                        loading={sendingVacationRequest}
                        disabled={sendingVacationRequest}
                        children={t("vacation.cancelVacation")}
                        onClick={()=>openCancelVacation(vacation.id)}
                  />}
                  {vacation.can_edit && <Button
                        variant="secondary"
                        className={styles.sendRequest}
                        loading={sendingVacationRequest}
                        disabled={sendingVacationRequest}
                        children={t("vacation.update")}
                        onClick={()=>openUpdateVacation(vacation)}
                  />}</div>}
            </div>
          </Card>
        ))}
        <Pagination {...paging} onPageChange={getVacationsList} />
        <Button
          className={styles.addReqeust}
          disabled={!extraData?.can_request}
          onClick={openAddVacation}
        >
          {t("vacation.addRequest")}
        </Button>
        
      </div>
        {vacationState && addVacationFormComponent}
        {cancelVacationState && cancelVacationModel}
    </section></>
  );
}