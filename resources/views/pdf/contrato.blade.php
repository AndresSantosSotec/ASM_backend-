<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: "Arial", sans-serif;
      font-size: 12px;
      margin: 0;
      padding: 0;
    }
    .header {
      background: linear-gradient(90deg, #1e264d 0%, #26335b 100%);
      padding: 20px;
      text-align: center;
      color: white;
    }
    .header img {
      max-height: 40px;
    }
    .gold-line {
      height: 4px;
      background: #b08b4f;
    }
    .content {
      padding: 30px;
      line-height: 1.4;
    }
    .title {
      text-align: center;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .sub {
      text-align: center;
      font-size: 10px;
      color: #666;
      margin-bottom: 20px;
    }
    .signature-block {
      margin-top: 40px;
      display: flex;
      justify-content: space-between;
    }
    .sig {
      width: 45%;
      text-align: center;
    }
    .sig-line {
      border-top: 1px solid #000;
      margin: 60px 0 10px;
    }
    .sig-label {
      font-size: 10px;
      color: #666;
    }
  </style>
</head>
<body>

  <div class="header">
    {{-- Reemplaza con tu logo --}}
  </div>
  <div class="gold-line"></div>

  <div class="content">
    <h2 class="title">CONTRATO DE CONFIDENCIALIDAD Y COMPROMISO DE ESTUDIANTE</h2>
    <p class="sub">(Por favor firme ambas páginas en donde corresponde)</p>

    <p>
      En la ciudad de Guatemala, el día
      <strong>{{ $fecha }}</strong><br>
    </p>

    <p>
      Yo: <strong>{{ $student->nombre_completo }}</strong> me comprometo a mantener
      de manera estrictamente confidencial los precios corporativos otorgados por
      American School of Management para cursar mi programa de:<br>
      <strong>{{ $programa->abreviatura }} - {{ $programa->nombre_del_programa }}</strong>
    </p>

    <p>
      Asimismo, entiendo y acepto que mi participación en el acto de graduación de
      dicho programa es obligatoria e indispensable.
    </p>

    <p>
      <strong>Matrícula:</strong> Q{{ number_format($inscripcion, 2) }}<br>
      <strong>Mensualidad:</strong> Q{{ number_format($mensualidad, 2) }}
    </p>

    @if($convenio_id && $mensualidad)
      <p>
        Asimismo, acepto que, en caso de divulgar este precio y las condiciones
        preferenciales relacionadas con la duración del programa, perderé
        automáticamente dicho beneficio y deberé asumir el pago de la cuota
        vigente correspondiente al tiempo establecido. Cabe destacar que el
        porcentaje de beca aplica únicamente si el pago se realiza mediante
        depósito o transferencia bancaria, y no se aplica con otros medios de
        pago. Si el pago se efectúa por otros medios distintos a los mencionados,
        la cuota se ajustará de la siguiente manera:<br>
        <strong>Mensualidad:</strong> Q{{ number_format($mensualidad, 2) }}
      </p>
    @endif

    <p>
      Deseo que el cobro de mi mensualidad sea de manera automática:<br>
      <em>(EL COBRO SERÁ EN LOS PRIMEROS DÍAS DEL MES, APLICANDO EL PORCENTAJE DE BECA)</em>
    </p>

    <p>
      Confirmo que tengo:<br>
      — Declaración de estar plenamente informado(a) y de acuerdo con que mi día de
      estudio puede ser modificado durante el transcurso de la carrera, y que
      los cursos del área común pueden variar según la programación anual.
      Reconozco que, al inscribirme, me uniré a un canal de WhatsApp, cuya
      participación es obligatoria durante toda la duración de mi carrera, con
      el fin de mantenerme actualizado(a) sobre toda la información relevante.
    </p>

    <p>
      Asimismo, confirmo que estoy consciente de que, debido a la modalidad de
      estudio de mi programa, es indispensable tomar mis clases a través de una
      computadora con una conexión a internet estable, en un espacio adecuado, y
      con la cámara encendida en todo momento.
    </p>

    <p>
      Finalmente, autorizo a American School of Management a utilizar mis
      fotografías para fines de colaboración institucional en materiales
      impresos o digitales.
    </p>

    <p>
      Estoy plenamente informado(a) de que el plazo límite para la entrega de los
      documentos requeridos es durante el primer trimestre del programa. Entiendo
      que no cumplir con esta entrega dentro del período establecido
      representará un obstáculo para mi graduación y la emisión del título
      correspondiente.
    </p>

    <p>
      Reitero mi compromiso de no duplicar ni compartir materiales provenientes
      de la plataforma para fines distintos a la realización de los cursos. Está
      estrictamente prohibido replicar rúbricas, casos del CIC o cualquier
      material proporcionado por Harvard BP, ya que dichas acciones serán
      consideradas como plagio y estarán sujetas a las consecuencias
      correspondientes.
    </p>

    <p>
      En American School of Management, los estudiantes se comprometen a la
      excelencia académica desde el inicio de su programa. Se fomenta la búsqueda
      de altos promedios para obtener menciones honoríficas:
    </p>
    <ul>
      <li>Cum Laude: promedio de 96 puntos.</li>
      <li>Magna Cum Laude: promedio de 97 a 98 puntos.</li>
      <li>Summa Cum Laude: promedio de 99 a 100 puntos.</li>
    </ul>
    <p>
      Además, se espera que los estudiantes actúen con integridad y ética, siendo
      un ejemplo de dedicación e inspiración para sus compañeros.
    </p>

    <p>
      Asimismo, acepto que al realizar los pagos correspondientes a las
      mensualidades y gastos adicionales, me comprometo a enviar las boletas
      únicamente a las siguientes direcciones:
      <strong>contabilidad@american-edu.com</strong> o a los números de WhatsApp
      <strong>+502 4169-8467</strong> o <strong>+502 4138-1907</strong>. Se
      exceptúa el pago de inscripción, el cual deberá ser remitido directamente
      al asesor educativo. Está prohibido enviar boletas a direcciones distintas
      a las mencionadas anteriormente.
    </p>

    <p>
      Con pleno entendimiento y aceptación de las condiciones aquí establecidas,
      firmo en señal de conformidad con este contrato.
    </p>

    <div class="signature-block">
      <div class="sig">
        @if(!empty($signature))
          <img src="{{ $signature }}" style="max-height:100px;" alt="Firma estudiante">
        @endif
        <div class="sig-line"></div>
        <div class="sig-label">{{ $student->nombre_completo }}</div>
      </div>
      <div class="sig">
        <div class="sig-line"></div>
        <div class="sig-label">{{ $advisorName ?? 'Asesor Educativo' }}</div>
      </div>
    </div>
  </div>

</body>
</html>
