<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrol_conditional', language 'pt_br'.
 *
 * @package    enrol_conditional
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cannotenrolreason'] = 'Você não pode se inscrever neste curso: {$a}';
$string['canntenrol'] = 'A inscrição está desativada ou inativa';
$string['canntenrolearly'] = 'Você ainda não pode se inscrever; a inscrição começa em {$a}.';
$string['canntenrollate'] = 'Você não pode mais se inscrever, pois a inscrição terminou em {$a}.';
$string['capacitybusy'] = 'Muitas pessoas estão se inscrevendo neste curso agora. Tente novamente em instantes.';
$string['checkmodeevents'] = 'Quando algo de que as condições dependem muda';
$string['checkmodeevents_desc'] = 'Verificar novamente, por padrão, quando acontece um evento observado pelas condições.';
$string['checkmodelogin'] = 'Quando o usuário entra no site';
$string['checkmodelogin_desc'] = 'Verificar novamente, por padrão, as inscrições dos usuários quando eles entram no site.';
$string['checkmodeperiodic'] = 'Periodicamente, na tarefa agendada';
$string['checkmodeperiodic_desc'] = 'Verificar novamente, por padrão, as inscrições na tarefa agendada.';
$string['conditionaction'] = 'Quando as condições deixarem de ser atendidas';
$string['conditionaction_desc'] = 'Ação padrão quando uma nova verificação constata que um usuário inscrito não atende mais às condições.';
$string['conditionactionnone'] = 'Nada (apenas registrar)';
$string['conditionactionremove'] = 'Remover a inscrição';
$string['conditionactionsuspend'] = 'Suspender a inscrição';
$string['conditional:config'] = 'Configurar instâncias de autoinscrição';
$string['conditional:enrolself'] = 'Autoinscrição no curso';
$string['conditional:holdkey'] = 'Aparecer como titular da chave de autoinscrição';
$string['conditional:manage'] = 'Gerenciar usuários inscritos';
$string['conditional:unenrol'] = 'Cancelar a inscrição de usuários do curso';
$string['conditional:unenrolself'] = 'Cancelar a própria inscrição no curso';
$string['conditionreactivate'] = 'Reativar quando as condições voltarem a ser atendidas';
$string['conditionreactivate_desc'] = 'Reativar, por padrão, as inscrições suspensas por este plugin quando as condições voltarem a ser atendidas.';
$string['conditions'] = 'Condições';
$string['conditions_help'] = 'O que um usuário precisa atender para se inscrever por este método. As condições são combinadas em grupos, e um grupo pode conter outros grupos.

* **Todas estas**: o grupo é atendido quando todos os seus itens são atendidos.
* **Qualquer uma destas**: o grupo é atendido quando pelo menos um dos seus itens é atendido.
* **Não deve ser atendida**: inverte uma condição ou um grupo, que passa a ser atendido só quando o que descreve não é verdade.

**Quando usar cada uma**

* Só um departamento, e só a partir de certa data: todas estas, com uma condição "Perfil do usuário" e uma condição "Data".
* Membros de qualquer uma de duas coortes: qualquer uma destas, com duas condições "Coorte".
* Todos, menos quem já concluiu o curso introdutório: uma condição "Curso concluído" marcada como não deve ser atendida.

**Exemplo**: todas estas, com "Coorte" igual a "Estagiários 2027" e "Curso concluído" igual a "Segurança básica", esta marcada como não deve ser atendida: os estagiários de 2027 que ainda não fizeram o curso de segurança podem se inscrever.

Sem condições, todos que podem se autoinscrever são aceitos. As condições são verificadas no momento da inscrição, do mesmo modo na página de inscrição, no aplicativo móvel e pelo próprio método. O link "Inscreva-me" e o ícone na lista de cursos continuam visíveis para quem não atende às condições, e a página de inscrição mostra quais condições faltam. Uma condição que não pode ser avaliada, por exemplo porque o seu tipo foi desinstalado, recusa toda nova inscrição até que um professor a corrija. As inscrições existentes podem ser verificadas de novo no login, periodicamente ou em segundo plano logo depois que algo de que as condições dependem muda ("Verificar as condições"); a que não passar é então suspensa, removida ou apenas registrada ("Quando as condições deixarem de ser atendidas"), e só as suspensões feitas por este método são reativadas.';
$string['conditionsnotmet'] = 'Você não pode se inscrever neste curso porque:';
$string['conditionsnotmetshort'] = 'Você não atende às condições para se inscrever neste curso.';
$string['confirmbulkdeleteenrolment'] = 'Tem certeza de que deseja excluir estas inscrições de usuários?';
$string['defaultrole'] = 'Atribuição de papel padrão';
$string['defaultrole_desc'] = 'Selecione o papel que deve ser atribuído aos usuários na autoinscrição.';
$string['deleteselectedusers'] = 'Excluir as inscrições dos usuários selecionados';
$string['editselectedusers'] = 'Editar as inscrições dos usuários selecionados';
$string['enroledas'] = '{$a->enrol} como \'{$a->role}\'';
$string['enrolenddate'] = 'Data final';
$string['enrolenddate_help'] = 'O último momento em que os usuários podem se inscrever por este método.

* Até lá, os usuários que atendem às condições podem se inscrever.
* Depois, o link "Inscreva-me" e o ícone na lista de cursos desaparecem, e a página de inscrição informa que a inscrição terminou.
* Quem já está inscrito mantém a inscrição: quanto tempo ela dura é definido em "Duração da inscrição".

**Exemplo**: habilitada e definida para 15 de março de 2027, 23:59, para encerrar a inscrição de uma turma que começa em 16 de março.

Sem efeito enquanto a caixa "Habilitar" não estiver marcada: a inscrição fica então aberta sem data de término. Não pode ser anterior à "Data de início".';
$string['enrolenddaterror'] = 'A data final da inscrição não pode ser anterior à data de início';
$string['enrolkeyrequired'] = 'Uma chave de inscrição será exigida.';
$string['enrolme'] = 'Inscreva-me';
$string['enrolperiod'] = 'Duração da inscrição';
$string['enrolperiod_desc'] = 'Duração padrão da validade da inscrição. Se for zero, a duração da inscrição será ilimitada por padrão.';
$string['enrolperiod_help'] = 'Quanto tempo dura cada inscrição feita por este método, contado a partir do momento em que o usuário se inscreve.

* Cada usuário recebe a sua própria data de término: o momento em que se inscreveu mais esta duração.
* Quando ela passa, o usuário não consegue mais entrar no curso, e a "Ação após o término da inscrição" do site decide se a inscrição é mantida, suspensa com os papéis removidos, ou removida.
* "Notificar antes da inscrição expirar" pode avisar o professor, e o usuário, com antecedência.
* Uma alteração vale só para as inscrições feitas depois; as datas de término já dadas não mudam.

**Exemplo**: 90 dias, para um curso autoinstrucional que cada aluno deve concluir em até três meses depois de entrar.

Sem efeito enquanto a caixa "Habilitar" não estiver marcada: as inscrições então nunca terminam.';
$string['enrolstartdate'] = 'Data de início';
$string['enrolstartdate_help'] = 'O primeiro momento em que os usuários podem se inscrever por este método.

* Antes dele, o link "Inscreva-me" e o ícone na lista de cursos ficam ocultos, e a página de inscrição informa quando a inscrição começa.
* A partir dele, os usuários que atendem às condições podem se inscrever, até a "Data final" quando ela estiver definida.
* Não atrasa o acesso: quem se inscreve entra no curso imediatamente.

**Exemplo**: habilitada e definida para 1º de fevereiro de 2027, 08:00, para abrir a inscrição no primeiro dia da semana de inscrições.

Sem efeito enquanto a caixa "Habilitar" não estiver marcada: os usuários podem então se inscrever imediatamente.';
$string['eventconditionsnotmet'] = 'Condições de inscrição não mais atendidas';
$string['eventuserenrolmentreactivated'] = 'Inscrição de usuário reativada pelas condições';
$string['eventuserenrolmentsuspended'] = 'Inscrição de usuário suspensa pelas condições';
$string['expiredaction'] = 'Ação após o término da inscrição';
$string['expiredaction_desc'] = 'O que acontece, em todo o site, quando uma inscrição feita por este método chega à data de término. Cancelar a inscrição do usuário apaga alguns dos seus dados e configurações no curso.';
$string['expiredaction_help'] = 'O que acontece com uma inscrição feita por este método quando passa a sua data de término. Vale para todos os cursos do site.

* **Manter usuário inscrito**: a inscrição continua, mas o usuário não consegue mais entrar no curso. Dar à inscrição uma data de término posterior devolve o acesso.
* **Desativar inscrição no curso e remover papéis**: a inscrição é suspensa e os papéis que ela dava no curso são removidos.
* **Desinscrever usuário do curso**: a inscrição é removida, e alguns dos dados e configurações do usuário no curso, como a participação em grupos, são apagados.

**Quando usar cada uma**

* Os professores prorrogam à mão os alunos atrasados: manter usuário inscrito.
* Os alunos com inscrição vencida devem perder os papéis no curso, mas a inscrição deve ficar registrada: desativar inscrição no curso e remover papéis.
* Os alunos com inscrição vencida não devem deixar inscrição alguma: desinscrever usuário do curso.

**Exemplo**: manter usuário inscrito, num site em que os professores dão mais duas semanas aos atrasados pela página de participantes.

Sem efeito para inscrições sem data de término, isto é, quando a "Duração da inscrição" não está definida e ninguém deu à inscrição uma data de término à mão. É aplicada pela tarefa agendada "Tarefa de sincronização de autoinscrições".';
$string['expiryinactivemessageenrolledbody'] = 'Olá, {$a->user},

Sua inscrição no curso {$a->course} expira em {$a->timeend}, pois você não o acessou nos últimos {$a->inactivetime} dias.

Para manter sua inscrição ativa, entre no site e acesse <a href="{$a->url}">{$a->course}</a> antes de {$a->timeend}.';
$string['expiryinactivemessageenrolledsubject'] = 'Sua inscrição está expirando: {$a->course}';
$string['expirymessageenrolledbody'] = 'Prezado(a) {$a->user},

Esta é uma notificação de que sua inscrição no curso \'{$a->course}\' vai expirar em {$a->timeend}.

Se precisar de ajuda, entre em contato com {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Notificação de expiração da autoinscrição';
$string['expirymessageenrollerbody'] = 'A autoinscrição no curso \'{$a->course}\' vai expirar nos próximos {$a->threshold} para os seguintes usuários:

{$a->users}

Para prorrogar a inscrição deles, acesse {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Notificação de expiração da autoinscrição';
$string['expirynotifyall'] = 'Professor e usuário inscrito';
$string['expirynotifyenroller'] = 'Somente o professor';
$string['groupgone'] = 'Você está inscrito no curso, mas o grupo da sua chave não existe mais, então você não foi adicionado a um grupo.';
$string['groupkey'] = 'Usar chaves de inscrição de grupo';
$string['groupkey_desc'] = 'Usar chaves de inscrição de grupo por padrão.';
$string['groupkey_help'] = 'Se a chave de inscrição de um grupo do curso também permite a inscrição por este método e coloca o usuário nesse grupo.

* **Sim**: o usuário que digita a chave de um grupo é inscrito e adicionado a esse grupo. A "Chave de inscrição" do próprio método continua valendo, e não coloca o usuário em grupo algum.
* **Não**: só a "Chave de inscrição" do próprio método é aceita.

**Quando usar cada uma**

* Cada turma recebe a sua chave, e os alunos devem cair no grupo da sua turma: sim.
* Uma chave para todos, e o professor monta os grupos depois: não.

**Exemplo**: sim, com "Iris-2027" no "Código de inscrição" do grupo "Turma A" e "Lotus-2027" no da "Turma B"; a chave do próprio método só os professores conhecem.

Sem efeito enquanto a "Chave de inscrição" estiver vazia: as chaves de grupo só são aceitas quando o método tem uma chave própria. A chave de um grupo é definida na página de configurações desse grupo, e a chave do método não pode ser igual a nenhuma delas. Qualquer que seja a chave usada, as condições continuam sendo verificadas, e uma chave errada nunca mostra a dica.';
$string['keyholder'] = 'Você deve ter recebido esta chave de inscrição de:';
$string['longtimenosee'] = 'Cancelar a inscrição de inativos após';
$string['longtimenosee_desc'] = 'Padrão para novas instâncias: por quanto tempo um usuário pode ficar sem abrir o curso, ou sem entrar no site, antes de ter a inscrição cancelada.';
$string['longtimenosee_help'] = 'Por quanto tempo um usuário inscrito por este método pode ficar ausente antes de ter a inscrição cancelada.

* **Nunca**: ninguém tem a inscrição cancelada por inatividade.
* **7 dias** a **1800 dias**: o usuário que não abre o curso há esse tempo, ou não entra no site há esse tempo, tem a inscrição cancelada, e alguns dos seus dados e configurações no curso são apagados.

**Quando usar cada uma**

* Os alunos ocupam vagas que outros estão esperando: um período curto, como 30 dias, junto com "Máximo de usuários inscritos".
* Um curso de consulta, ao qual as pessoas voltam de vez em quando: nunca.

**Exemplo**: 60 dias, com "Notificar antes da inscrição expirar" definido como "Professor e usuário inscrito" e um "Limite de notificação" de 7 dias, para que os alunos sejam avisados uma semana antes.

É aplicado pela tarefa agendada "Tarefa de sincronização de autoinscrições", também às inscrições suspensas. Quem nunca abriu o curso só tem a inscrição cancelada quando não entra no site há esse tempo.';
$string['maxenrolled'] = 'Máximo de usuários inscritos';
$string['maxenrolled_desc'] = 'Número máximo padrão de usuários que podem estar inscritos ao mesmo tempo por uma nova instância. 0 significa sem limite.';
$string['maxenrolled_help'] = 'O maior número de usuários que podem estar inscritos por este método ao mesmo tempo.

* 0 significa sem limite.
* Quando esse número de usuários está inscrito, o link "Inscreva-me" e o ícone na lista de cursos desaparecem, e mais ninguém consegue se inscrever até que uma vaga seja liberada.
* As vagas são contadas de novo no exato momento da inscrição, de modo que duas pessoas inscrevendo-se ao mesmo tempo nunca ficam ambas com a última vaga: uma delas é avisada de que o curso está cheio.
* Uma inscrição suspensa, inclusive a suspensa porque as condições deixaram de ser atendidas, continua ocupando uma vaga; uma inscrição removida a libera.

**Exemplo**: 40, para uma oficina com 40 lugares.

Só contam as inscrições feitas por este método: usuários inscritos por qualquer outro método não ocupam vaga.';
$string['maxenrolledreached'] = 'O número máximo de usuários que podem se autoinscrever já foi atingido.';
$string['messageprovider:expiry_notification'] = 'Notificações de expiração da autoinscrição';
$string['newenrols'] = 'Permitir novas autoinscrições';
$string['newenrols_desc'] = 'Permitir, por padrão, que os usuários se autoinscrevam em novos cursos.';
$string['newenrols_help'] = 'Se novos usuários ainda podem se inscrever por este método.

* **Sim**: os usuários que podem se autoinscrever e atendem às condições conseguem se inscrever, entre a "Data de início" e a "Data final" e enquanto houver vagas.
* **Não**: ninguém novo consegue se inscrever, e o link "Inscreva-me" e o ícone na lista de cursos desaparecem. Quem já está inscrito mantém o acesso, e as suas inscrições continuam sendo verificadas pelas condições.

**Quando usar cada uma**

* A inscrição está aberta: sim.
* A turma está cheia ou fechada, mas o curso continua para quem já está nele: não.

**Exemplo**: não, quando os 40 alunos de uma coorte já entraram e o professor não quer inscrições tardias.

Sem efeito enquanto "Manter ativas as autoinscrições atuais" estiver em "Não": o campo fica então desabilitado, pois o método não aceita inscrição alguma.';
$string['nopassword'] = 'Nenhuma chave de inscrição é necessária.';
$string['password'] = 'Chave de inscrição';
$string['password_help'] = 'Uma chave que os usuários precisam digitar para se inscrever por este método, com no máximo 50 caracteres.

* Vazia, ninguém precisa digitar uma chave.
* Preenchida, a chave é pedida uma vez, na inscrição, e nunca mais. Ela é verificada do mesmo modo na página de inscrição, no aplicativo móvel e pelo próprio método.
* Alterar ou remover a chave não afeta quem já está inscrito.
* Com "Usar chaves de inscrição de grupo" em "Sim", a chave de um grupo do curso também é aceita.

**Exemplo**: "Porto-2027", entregue à turma no primeiro dia.

A chave não substitui as condições: quem a conhece mas não atende às condições não consegue se inscrever. O site pode exigir uma chave em todos os métodos, aplicar a ela a política de senhas e mostrar a sua primeira letra depois de uma tentativa errada.';
$string['passwordinvalid'] = 'Chave de inscrição incorreta, tente novamente';
$string['passwordinvalidhint'] = 'Essa chave de inscrição está incorreta, tente novamente<br />
(Uma dica: ela começa com \'{$a}\')';
$string['passwordmatchesgroupkey'] = 'Esta chave de inscrição já é usada como chave de inscrição de grupo.';
$string['pluginname'] = 'Autoinscrição condicional';
$string['pluginname_desc'] = 'O plugin de autoinscrição condicional permite que os usuários escolham de quais cursos querem participar, desde que atendam às condições configuradas em cada método de inscrição. Os cursos podem ser protegidos por uma chave de inscrição.';
$string['privacy:metadata'] = 'O plugin Autoinscrição condicional não tem tabelas próprias. As inscrições, atribuições de papéis e participações em grupos que ele cria são guardadas e cobertas pelo núcleo do Moodle. Os registros das ações tomadas quando as condições deixam de ser atendidas, e as listas de usuários que algumas condições guardam nas configurações da instância, são cobertos pelo plugin Condições de inscrição e pelos seus tipos de condição.';
$string['requirepassword'] = 'Exigir chave de inscrição';
$string['requirepassword_desc'] = 'Exigir chave de inscrição em novos cursos e impedir a remoção da chave de inscrição dos cursos existentes.';
$string['role'] = 'Papel atribuído por padrão';
$string['sendcoursewelcomemessage'] = 'Enviar mensagem de boas-vindas ao curso';
$string['sendcoursewelcomemessage_desc'] = 'Padrão para novas instâncias: se os usuários que se autoinscrevem recebem a mensagem de boas-vindas ao curso, e de quem.';
$string['sendcoursewelcomemessage_help'] = 'Se os usuários que se inscrevem por este método recebem uma mensagem de boas-vindas, e quem a envia.

* **Não**: nenhuma mensagem é enviada.
* **Do contato da disciplina**: enviada pelo primeiro usuário do curso que tem um dos papéis que o site lista em "Administradores de curso", normalmente o professor. Se ninguém no curso tem esses papéis, nenhuma mensagem é enviada.
* **Do titular da chave**: enviada pelo primeiro usuário do curso que tem a capacidade "Aparecer como titular da chave de autoinscrição". Se ninguém a tem, nenhuma mensagem é enviada.
* **Do endereço de "não responda" (no-reply)**: enviada do endereço de não resposta do site, de modo que as respostas não chegam a ninguém.

**Quando usar cada uma**

* Os alunos devem poder responder ao professor: do contato da disciplina.
* Um coordenador entrega a chave e responde às dúvidas: do titular da chave.
* Um curso aberto e grande, em que ninguém responde e-mails: do endereço de "não responda".

**Exemplo**: do contato da disciplina, com uma "Mensagem de boas-vindas padrão" que traz a data e o link do primeiro encontro ao vivo.

O texto é definido em "Mensagem de boas-vindas padrão", que aparece a menos que esta opção seja "Não". Usuários inscritos por outros métodos não recebem esta mensagem.';
$string['sendexpirynotificationstask'] = 'Tarefa de envio de notificações de expiração da autoinscrição';
$string['showhint'] = 'Mostrar dica';
$string['showhint_desc'] = 'Mostrar a primeira letra da chave de inscrição depois que uma chave errada é digitada, exceto nos métodos que aceitam chaves de inscrição de grupo.';
$string['status'] = 'Manter ativas as autoinscrições atuais';
$string['status_desc'] = 'Ativar o método de autoinscrição em novos cursos.';
$string['status_help'] = 'Se este método de inscrição está ligado.

* **Sim**: os usuários inscritos por este método conseguem entrar no curso, e novos usuários podem se inscrever enquanto "Permitir novas autoinscrições" estiver em "Sim".
* **Não**: o método está desligado. Os usuários inscritos por ele não conseguem mais entrar no curso, a menos que outro método também os inscreva; ninguém consegue se inscrever por ele; e as suas inscrições deixam de ser verificadas pelas condições.

**Quando usar cada uma**

* O curso está em andamento: sim.
* O curso terminou e os alunos não devem mais entrar nele, mas as inscrições devem ficar registradas: não.
* Só as novas inscrições devem parar: sim, com "Permitir novas autoinscrições" em "Não".

**Exemplo**: não, no fim do semestre, para que as inscrições e as notas fiquem registradas mas ninguém entre mais no curso.

Voltar para "Sim" devolve o acesso a todos que ainda estão inscritos. Enquanto estiver em "Não", nenhum aviso de expiração é enviado, mas "Cancelar a inscrição de inativos após" continua cancelando a inscrição dos usuários inativos.';
$string['syncenrolmentstask'] = 'Tarefa de sincronização de autoinscrições';
$string['unenrol'] = 'Cancelar a inscrição do usuário';
$string['unenrolselfconfirm'] = 'Você realmente deseja cancelar sua inscrição no curso "{$a}"?';
$string['unenroluser'] = 'Você realmente deseja cancelar a inscrição de "{$a->user}" no curso "{$a->course}"?';
$string['unenrolusers'] = 'Cancelar a inscrição de usuários';
$string['usepasswordpolicy'] = 'Usar política de senhas';
$string['usepasswordpolicy_desc'] = 'Usar a política de senhas padrão para as chaves de inscrição.';
