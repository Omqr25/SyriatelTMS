import Card from "../../../../components/ui/Card.jsx";
import Badge from "../../../../components/ui/Badge.jsx";
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

  const getBadgeTone = (state) => {
  switch (state) {
    case "accepted":
      return "green";
    case "pending":
      return "amber";
    case "rejected":
      return "red";
    default:
      return "amber";
  }
};
  const { t } = useTranslation("trainee");
    const {
        vacationRequest,
        sendingVacationRequest,
        updatingVacationRequest,
        vacationsList,
        vacationState,
        cancelVacationState,
        loadingCancelVacationState,
        loadingAction,
        getVacatoinsList,
        openCancelVacation,
        openUpdateVacation,
        sendCancelVacation,
        sendUpdateVacation,
        sendVacationRequest,
        setAddVacation,setVacationRequest
    } = VacationHooks()

  useEffect(()=>{
    getVacatoinsList()
  },[])

  

  const getToday = () => {
  const date = new Date();
  date.setDate(date.getDate()+1)
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};


    const sendActionBasedOnData=(e)=>{
      e.preventDefault(); 
      vacationRequest.reason ===""?
      sendVacationRequest():
      sendUpdateVacation()
    }
    

  

  const showCancelVacation = (status,startDate)=>{
    const currentDate = getToday()
    return (status === "pending" && currentDate < startDate)
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
                        children={<Input type={"date"} min={getToday()}
                        value={vacationRequest.start_date} 
                        onChange={(e)=>setVacationRequest((pre)=>({...pre,"start_date":e.target.value.split(" ")[0]}))} />}/>
                    <Field key={"endDate"}
                        label={t("vacation.endDate")} 
                        children={
                        <Input type={"date"} min={getToday()}
                        value={vacationRequest.end_date}
                        onChange={(e)=>setVacationRequest((pre)=>({...pre,"end_date":e.target.value.split(" ")[0]}))}/>}/>
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
          {vacationsList.length} {t("vacation.requests")}
        </span>
      </header>
      <div className={styles.list}>
        {vacationsList.map((vacation) => (
          <Card
            key={vacation.id }
            className={`${styles.card} ${styles[vacation.state]}`}
          >
            <div className={styles.cardHeader}>
              <div className={styles.reasonGroup}>
                <h3 className={styles.reason}>
                  {vacation.reason}
                </h3>
              </div>
              <Badge tone={getBadgeTone(vacation.state)}>
                {t(`vacation.${vacation.status}`)}
              </Badge>
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
                {t("vacation.submitted")} {vacation.submitted_at}
              </span>
              {showCancelVacation(vacation.status,vacation.start_date)&&<div
              className={styles.container}>
                <Button 
                        variant="soft"
                        className={styles.sendRequest}
                        loading={sendingVacationRequest}
                        disabled={sendingVacationRequest}
                        children={t("vacation.cancelVacation")}
                        onClick={()=>openCancelVacation(vacation.id)}
                  />
                  <Button 
                        variant="secondary"
                        className={styles.sendRequest}
                        loading={sendingVacationRequest}
                        disabled={sendingVacationRequest}
                        children={t("vacation.update")}
                        onClick={()=>openUpdateVacation(vacation)}
                  /></div>}
            </div>
          </Card>
        ))}
        <Button className={styles.addReqeust} children={t("vacation.addRequest")} onClick={()=>setAddVacation(true)}/>
        
      </div>
        {vacationState && addVacationFormComponent}
        {cancelVacationState && cancelVacationModel}
    </section></>
  );
}